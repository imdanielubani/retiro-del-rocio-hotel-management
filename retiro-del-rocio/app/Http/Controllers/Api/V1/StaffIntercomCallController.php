<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\IntercomCallSignal;
use App\Http\Controllers\Controller;
use App\Models\IntercomCall;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Internal staff Intercom — any staff tablet (Reception, Housekeeping,
 * Maintenance, Security) voice-calling any other station, the mesh
 * counterpart of {@see StaffChatController}. The caller's own role is read
 * off their staff JWT, never the URL, so a token can only ever act as the
 * role it actually holds.
 *
 * Reception's own screen also uses this for its Staff tab; its Guests tab
 * (calling/receiving from an in-house guest room) is served separately by
 * {@see ReceptionIntercomCallController} — both write to the same
 * {@see IntercomCall} table, so either controller's `current`/`answer`/
 * `decline`/`end` sees the same call regardless of which one placed it.
 */
class StaffIntercomCallController extends Controller
{
    private const TABLET_ROLES = ['reception', 'housekeeping', 'maintenance', 'security'];

    /** POST /staff/intercom/calls — call another station. */
    public function store(Request $request): JsonResponse
    {
        $me = $this->myRole($request);
        $data = $request->validate(['role' => ['required', 'string']]);
        $other = $this->otherRole($me, $data['role']);

        abort_if($this->activeCallFor($me), 409, 'A call is already in progress.');
        abort_if($this->activeCallFor($other), 409, 'That station already has a call in progress.');

        $call = IntercomCall::create([
            'from_role' => $me,
            'from_label' => $this->labelFor($me),
            'to_role' => $other,
            'to_label' => $this->labelFor($other),
            'status' => IntercomCall::RINGING,
        ]);

        return response()->json(['data' => $call->toCallArray()], 201);
    }

    /** GET /staff/intercom/calls/current — this station's active call, either side. */
    public function current(Request $request): JsonResponse
    {
        $me = $this->myRole($request);

        $call = IntercomCall::current()
            ->where('from_role', $me)
            ->orWhere(fn ($q) => $q->current()->where('to_role', $me))
            ->latest('created_at')
            ->first();

        return response()->json(['data' => $call?->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/answer — accept an incoming call. */
    public function answer(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myRole($request);
        abort_unless($call->isCallee(null, $me), 403, 'Not this station\'s call.');
        abort_unless($call->accept(), 409, 'This call can no longer be answered.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/decline — decline an incoming call. */
    public function decline(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myRole($request);
        abort_unless($call->isCallee(null, $me), 403, 'Not this station\'s call.');
        abort_unless($call->decline(), 409, 'This call can no longer be declined.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/end — hang up, whichever side this station is on. */
    public function end(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myRole($request);
        $isParty = $call->isCaller(null, $me) || $call->isCallee(null, $me);
        abort_unless($isParty, 403, 'Not this station\'s call.');
        abort_unless($call->hangUp(), 409, 'This call has already ended.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /**
     * POST /staff/intercom/calls/{call}/signal — relay a WebRTC
     * offer/answer/ICE candidate to the other side of this call.
     */
    public function signal(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myRole($request);
        $isParty = $call->isCaller(null, $me) || $call->isCallee(null, $me);
        abort_unless($isParty, 403, 'Not this station\'s call.');
        abort_unless(in_array($call->status, IntercomCall::ACTIVE_STATUSES, true), 409, 'This call is no longer active.');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:offer,answer,ice-candidate'],
            'data' => ['required', 'array'],
        ]);

        $from = $call->isCaller(null, $me) ? 'caller' : 'callee';

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
            ->first();
    }

    private function labelFor(string $role): string
    {
        return ucfirst($role);
    }

    /** [$role] validated as a real, different-from-caller station. */
    private function otherRole(string $me, string $role): string
    {
        abort_unless(in_array($role, self::TABLET_ROLES, true) && $role !== $me, 404, 'Unknown station.');

        return $role;
    }

    /** The calling staff member's own role, which must be one of the tablet roles. */
    private function myRole(Request $request): string
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $role = collect(self::TABLET_ROLES)->first(fn (string $r) => $user->hasRole($r));
        abort_unless($role !== null, 403, 'Not a recognised staff role.');

        return $role;
    }
}
