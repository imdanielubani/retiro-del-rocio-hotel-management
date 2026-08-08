<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\IntercomCallSignal;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\IntercomCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The guest tablet's Intercom — placing and receiving a call with Reception.
 * The device's own room is always one party; the other today is always
 * Reception, the only staff station with a receiving screen built.
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
     * POST /intercom/calls/{call}/signal — relay a WebRTC offer/answer/ICE
     * candidate to the other side of this call.
     */
    public function signal(Request $request, IntercomCall $call): JsonResponse
    {
        $device = $this->guestDevice($request);
        $isParty = $call->isCaller($device->room_unit_id, null) || $call->isCallee($device->room_unit_id, null);
        abort_unless($isParty, 403, 'Not this room\'s call.');
        abort_unless(in_array($call->status, IntercomCall::ACTIVE_STATUSES, true), 409, 'This call is no longer active.');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:offer,answer,ice-candidate'],
            'data' => ['required', 'array'],
        ]);

        $from = $call->isCaller($device->room_unit_id, null) ? 'caller' : 'callee';

        try {
            broadcast(new IntercomCallSignal($call->id, $from, $data['type'], $data['data']));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => true]);
    }

    private function activeCallFor(Device $device): ?IntercomCall
    {
        return IntercomCall::active()
            ->where('from_room_unit_id', $device->room_unit_id)
            ->orWhere(function ($q) use ($device) {
                $q->active()->where('to_room_unit_id', $device->room_unit_id);
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
