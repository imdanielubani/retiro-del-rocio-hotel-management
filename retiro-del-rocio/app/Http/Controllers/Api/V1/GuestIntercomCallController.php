<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\IntercomCall;
use App\Models\RoomUnit;
use App\Support\AgoraTokenBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The guest tablet's Intercom — placing and receiving a call with Reception,
 * or with another checked-in guest's room directly (Room to Room, dialled by
 * room number — see {@see lookupRoom()}/{@see callRoom()}). The device's own
 * room is always one party. The actual voice audio is Agora's problem, not
 * this app's — see {@see token()}.
 */
class GuestIntercomCallController extends Controller
{
    /** POST /intercom/calls — call Reception from this room. */
    public function store(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        abort_if($this->activeCallFor($device), 409, 'This room already has a call in progress.');

        $call = IntercomCall::create([
            'from_room_unit_id' => $device->room_unit_id,
            'from_label' => 'Room '.($device->roomUnit?->number ?? '?'),
            'from_sublabel' => $device->currentBooking()?->customer_name,
            'to_role' => 'reception',
            'to_label' => 'Reception',
            'to_sublabel' => 'Front desk assistance',
            'status' => IntercomCall::RINGING,
        ]);

        return response()->json(['data' => $call->toCallArray()], 201);
    }

    /** GET /intercom/calls/current — this room's active call, either side. */
    public function current(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $call = IntercomCall::current()
            ->where('from_room_unit_id', $device->room_unit_id)
            ->orWhere(function ($q) use ($device) {
                $q->current()->where('to_room_unit_id', $device->room_unit_id);
            })
            ->latest('created_at')
            ->first();

        return response()->json(['data' => $call?->toCallArray()]);
    }

    /** POST /intercom/calls/{call}/answer — accept an incoming call from Reception. */
    public function answer(Request $request, IntercomCall $call): JsonResponse
    {
        $device = $this->guestDevice($request);
        abort_unless($call->isCallee($device->room_unit_id, null), 403, 'Not this room\'s call.');
        abort_unless($call->accept(), 409, 'This call can no longer be answered.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /intercom/calls/{call}/decline — decline an incoming call from Reception. */
    public function decline(Request $request, IntercomCall $call): JsonResponse
    {
        $device = $this->guestDevice($request);
        abort_unless($call->isCallee($device->room_unit_id, null), 403, 'Not this room\'s call.');
        abort_unless($call->decline(), 409, 'This call can no longer be declined.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /intercom/calls/{call}/end — hang up, whichever side of the call this room is on. */
    public function end(Request $request, IntercomCall $call): JsonResponse
    {
        $device = $this->guestDevice($request);
        $isParty = $call->isCaller($device->room_unit_id, null) || $call->isCallee($device->room_unit_id, null);
        abort_unless($isParty, 403, 'Not this room\'s call.');
        abort_unless($call->hangUp(), 409, 'This call has already ended.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /**
     * GET /intercom/calls/{call}/token — this device's Agora credentials for
     * the call's voice channel. Called once the call is accepted; from here
     * on Agora's own relay infrastructure carries the actual audio, not this
     * app — the channel is just `intercom-{callId}`, and `uid` only needs to
     * be distinct between the two parties, never globally unique.
     */
    public function token(Request $request, IntercomCall $call): JsonResponse
    {
        $device = $this->guestDevice($request);
        $isParty = $call->isCaller($device->room_unit_id, null) || $call->isCallee($device->room_unit_id, null);
        abort_unless($isParty, 403, 'Not this room\'s call.');
        abort_unless(in_array($call->status, IntercomCall::ACTIVE_STATUSES, true), 409, 'This call is no longer active.');

        $uid = $call->isCaller($device->room_unit_id, null) ? 1 : 2;
        $channel = 'intercom-'.$call->id;

        return response()->json(['data' => [
            'app_id' => config('services.agora.app_id'),
            'channel' => $channel,
            'uid' => $uid,
            'token' => AgoraTokenBuilder::forUid($channel, $uid),
        ]]);
    }

    /**
     * GET /intercom/rooms/{number} — Room to Room's live dial-pad lookup.
     * Resolves a room number to the guest currently checked into it, or
     * `found: false` for a number that doesn't exist, isn't occupied right
     * now, or is this device's own room (the guest can see their own room
     * "found" but can't call it — see [$isOwnRoom] in the response).
     */
    public function lookupRoom(Request $request, string $number): JsonResponse
    {
        $device = $this->guestDevice($request);

        $unit = RoomUnit::with(['room', 'booking'])->where('number', $number)->first();
        $booking = $unit?->booking;
        $checkedIn = $unit && $booking && $booking->status === 'checked_in';

        if (! $checkedIn) {
            return response()->json(['data' => ['found' => false]]);
        }

        return response()->json(['data' => [
            'found' => true,
            'number' => $unit->number,
            'suite_label' => $unit->room?->name,
            'room_type_label' => $unit->room?->type,
            'guest_name' => $booking->customer_name,
            'is_own_room' => $unit->id === $device->room_unit_id,
        ]]);
    }

    /**
     * POST /intercom/calls/room — Room to Room: call another checked-in
     * guest's room directly by room number, no staff involved. Shares the
     * exact same [IntercomCall] state machine and Agora token flow as a
     * guest↔Reception call ({@see store()}/{@see token()}) — only the
     * callee is a room instead of Reception.
     */
    public function callRoom(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);
        abort_if($this->activeCallFor($device), 409, 'This room already has a call in progress.');

        $data = $request->validate(['room_number' => ['required', 'string', 'max:10']]);

        $target = RoomUnit::with('booking')->where('number', $data['room_number'])->first();
        abort_if($target && $target->id === $device->room_unit_id, 422, 'You can\'t call your own room.');

        $booking = $target?->booking;
        $checkedIn = $target && $booking && $booking->status === 'checked_in';
        abort_unless($checkedIn, 404, "Room {$data['room_number']} is not checked in.");

        abort_if($this->activeCallForRoomUnit($target->id), 409, 'That room already has a call in progress.');

        $call = IntercomCall::create([
            'from_room_unit_id' => $device->room_unit_id,
            'from_label' => 'Room '.($device->roomUnit?->number ?? '?'),
            'from_sublabel' => $device->currentBooking()?->customer_name,
            'to_room_unit_id' => $target->id,
            'to_label' => 'Room '.$target->number,
            'to_sublabel' => $booking->customer_name,
            'status' => IntercomCall::RINGING,
        ]);

        return response()->json(['data' => $call->toCallArray()], 201);
    }

    private function activeCallFor(Device $device): ?IntercomCall
    {
        return $this->activeCallForRoomUnit($device->room_unit_id);
    }

    private function activeCallForRoomUnit(?int $roomUnitId): ?IntercomCall
    {
        if ($roomUnitId === null) {
            return null;
        }

        return IntercomCall::active()
            ->where('from_room_unit_id', $roomUnitId)
            ->orWhere(function ($q) use ($roomUnitId) {
                $q->active()->where('to_room_unit_id', $roomUnitId);
            })
            ->latest('created_at')
            ->first();
    }

    /** The calling device, which must be a guest tablet bound to a room. */
    private function guestDevice(Request $request): Device
    {
        $device = $request->user();

        abort_unless($device instanceof Device, 403, 'Device token required.');
        abort_unless($device->room_unit_id, 409, 'This tablet is not assigned to a room.');

        return $device;
    }
}
