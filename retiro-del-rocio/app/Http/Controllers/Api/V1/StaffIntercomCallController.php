<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntercomCall;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\AgoraTokenBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal staff Intercom — any staff tablet voice-calling one specific
 * other staff member (or an admin-portal user), the mesh counterpart of
 * {@see StaffChatController}. The caller's own identity is read off their
 * staff JWT, never the URL, so a token can only ever act as the user it
 * actually belongs to.
 *
 * A call is addressed to one person, not a role — two accounts holding the
 * same role (e.g. two `bar` waiters) can be rung individually rather than
 * every device with that role ringing at once.
 *
 * Reception's own screen also uses this for its Staff tab; its Guests tab
 * (calling/receiving from an in-house guest room) is served separately by
 * {@see ReceptionIntercomCallController} — both write to the same
 * {@see IntercomCall} table, so either controller's `current`/`answer`/
 * `decline`/`end` sees the same call regardless of which one placed it.
 */
class StaffIntercomCallController extends Controller
{
    private const TABLET_ROLES = StaffMessage::ROLES;

    /** POST /staff/intercom/calls — call another staff member. */
    public function store(Request $request): JsonResponse
    {
        $me = $this->myUser($request);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $target = $this->contact($me, (int) $data['user_id']);

        abort_if($this->activeCallFor($me->id), 409, 'A call is already in progress.');
        abort_if($this->activeCallFor($target->id), 409, 'That person already has a call in progress.');

        $call = IntercomCall::create([
            'from_user_id' => $me->id,
            'from_role' => $this->roleFor($me),
            'from_label' => $me->name,
            'to_user_id' => $target->id,
            'to_role' => $this->roleFor($target),
            'to_label' => $target->name,
            'status' => IntercomCall::RINGING,
        ]);

        return response()->json(['data' => $call->toCallArray()], 201);
    }

    /** GET /staff/intercom/calls/current — this staffer's active call, either side. */
    public function current(Request $request): JsonResponse
    {
        $me = $this->myUser($request);

        $call = IntercomCall::current()
            ->where('from_user_id', $me->id)
            ->orWhere(fn ($q) => $q->current()->where('to_user_id', $me->id))
            ->latest('created_at')
            ->first();

        return response()->json(['data' => $call?->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/answer — accept an incoming call. */
    public function answer(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myUser($request);
        abort_unless($call->isCallee(null, null, $me->id), 403, 'Not your call.');
        abort_unless($call->accept(), 409, 'This call can no longer be answered.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/decline — decline an incoming call. */
    public function decline(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myUser($request);
        abort_unless($call->isCallee(null, null, $me->id), 403, 'Not your call.');
        abort_unless($call->decline(), 409, 'This call can no longer be declined.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /** POST /staff/intercom/calls/{call}/end — hang up, whichever side this staffer is on. */
    public function end(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myUser($request);
        $isParty = $call->isCaller(null, null, $me->id) || $call->isCallee(null, null, $me->id);
        abort_unless($isParty, 403, 'Not your call.');
        abort_unless($call->hangUp(), 409, 'This call has already ended.');

        return response()->json(['data' => $call->fresh()->toCallArray()]);
    }

    /**
     * GET /staff/intercom/calls/{call}/token — this staffer's Agora
     * credentials for the call's voice channel.
     */
    public function token(Request $request, IntercomCall $call): JsonResponse
    {
        $me = $this->myUser($request);
        $isParty = $call->isCaller(null, null, $me->id) || $call->isCallee(null, null, $me->id);
        abort_unless($isParty, 403, 'Not your call.');
        abort_unless(in_array($call->status, IntercomCall::ACTIVE_STATUSES, true), 409, 'This call is no longer active.');

        $uid = $call->isCaller(null, null, $me->id) ? 1 : 2;
        $channel = 'intercom-'.$call->id;

        return response()->json(['data' => [
            'app_id' => config('services.agora.app_id'),
            'channel' => $channel,
            'uid' => $uid,
            'token' => AgoraTokenBuilder::forUid($channel, $uid),
        ]]);
    }

    private function activeCallFor(int $userId): ?IntercomCall
    {
        return IntercomCall::active()
            ->where('from_user_id', $userId)
            ->orWhere(fn ($q) => $q->active()->where('to_user_id', $userId))
            ->first();
    }

    /**
     * [$userId] validated as a real, reachable, different-from-caller staff
     * member. Uses `whereHas` rather than Spatie's `role()` scope, which
     * throws `RoleDoesNotExist` if any name in the list hasn't been
     * created yet instead of simply matching nothing.
     */
    private function contact(User $me, int $userId): User
    {
        $target = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_merge(self::TABLET_ROLES, StaffMessage::ADMIN_ROLES)))
            ->find($userId);

        abort_unless($target && ! $target->is($me), 404, 'Unknown staff member.');

        return $target;
    }

    /** [$user]'s own department role, or "admin" for an admin-portal user. */
    private function roleFor(User $user): string
    {
        $role = collect(self::TABLET_ROLES)->first(fn (string $r) => $user->hasRole($r));

        return $role ?? 'admin';
    }

    /** The calling staff member, who must hold one of the tablet roles. */
    private function myUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasAnyRole(self::TABLET_ROLES), 403, 'Not a recognised staff role.');

        return $user;
    }
}
