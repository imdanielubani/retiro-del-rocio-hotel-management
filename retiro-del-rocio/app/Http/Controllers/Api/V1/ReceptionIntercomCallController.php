<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\IntercomCallSignal;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\IntercomCall;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Reception's Intercom — placing a call to any checked-in guest's room, and
 * receiving a call a guest placed to the front desk.
 */
class ReceptionIntercomCallController extends Controller
{
    /** POST /reception/intercom/calls — call a checked-in guest's room. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->receptionist($request);

        $data = $request->validate(['booking_id' => ['required', 'integer']]);
        $booking = Booking::where('status', 'checked_in')->find($data['booking_id']);
        abort_unless($booking && $booking->room_unit_id, 404, 'That guest is not checked in.');

        abort_if($this->activeCallFor('reception'), 409, 'A call is already in progress.');
        abort_if(
            IntercomCall::active()->where('to_room_unit_id', $booking->room_unit_id)
                ->orWhere(fn ($q) => $q->active()->where('from_room_unit_id', $booking->room_unit_id))
                ->exists(),
            409,
            'That room already has a call in progress.',
        );

        $unit = $booking->roomUnit()->first();

        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'from_sublabel' => 'Front desk assistance',
            'to_room_unit_id' => $booking->room_unit_id,
            'to_label' => 'Room '.($unit?->number ?? '?'),
            'to_sublabel' => $booking->customer_name,
            'status' => IntercomCall::RINGING,
        ]);

        return response()->json(['data' => $call->toCallArray()], 201);
    }

    /** GET /reception/intercom/calls/current — the desk's active call, either side. */
    public function current(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $call = IntercomCall::current()
            ->where('from_role', 'reception')
            ->orWhere(fn ($q) => $q->current()->where('to_role', 'reception'))
            ->latest('created_at')
            ->first();

        return response()->json(['data' => $call?->toCallArray()]);
    }

    /** POST /reception/intercom/calls/{call}/answer — accept an incoming call from a guest. */
    public function answer(Request $request, IntercomCall $call): JsonResponse
    {
        $this->receptionist($request);
        abort_unless($call->isCallee(null, 'reception'), 403, 'Not this station\'s call.');
        abort_unless($call->accept(), 409, 'This call can no longer be answered.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /reception/intercom/calls/{call}/decline — decline an incoming call from a guest. */
    public function decline(Request $request, IntercomCall $call): JsonResponse
    {
        $this->receptionist($request);
        abort_unless($call->isCallee(null, 'reception'), 403, 'Not this station\'s call.');
        abort_unless($call->decline(), 409, 'This call can no longer be declined.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /reception/intercom/calls/{call}/end — hang up, whichever side of the call the desk is on. */
    public function end(Request $request, IntercomCall $call): JsonResponse
    {
        $this->receptionist($request);
        $isParty = $call->isCaller(null, 'reception') || $call->isCallee(null, 'reception');
        abort_unless($isParty, 403, 'Not this station\'s call.');
        abort_unless($call->hangUp(), 409, 'This call has already ended.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /**
     * POST /reception/intercom/calls/{call}/signal — relay a WebRTC
     * offer/answer/ICE candidate to the other side of this call.
     */
    public function signal(Request $request, IntercomCall $call): JsonResponse
    {
        $this->receptionist($request);
        $isParty = $call->isCaller(null, 'reception') || $call->isCallee(null, 'reception');
        abort_unless($isParty, 403, 'Not this station\'s call.');
        abort_unless(in_array($call->status, IntercomCall::ACTIVE_STATUSES, true), 409, 'This call is no longer active.');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:offer,answer,ice-candidate'],
            'data' => ['required', 'array'],
        ]);

        $from = $call->isCaller(null, 'reception') ? 'caller' : 'callee';

        try {
            broadcast(new IntercomCallSignal($call->id, $from, $data['type'], $data['data']));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => true]);
    }

    private function activeCallFor(string $role): ?IntercomCall
    {
        return IntercomCall::active()
            ->where('from_role', $role)
            ->orWhere(fn ($q) => $q->active()->where('to_role', $role))
            ->latest('created_at')
            ->first();
    }

    /** The calling staff member, who must hold the reception role. */
    private function receptionist(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('reception'), 403, 'Reception access only.');

        return $user;
    }
}
