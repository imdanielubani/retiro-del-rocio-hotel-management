<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StaffChatMessageSent;
use App\Events\StaffChatTypingSent;
use App\Http\Controllers\Controller;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Internal staff chat — every staff tablet's Chat screen (Reception,
 * Housekeeping, Maintenance, Security, Bar, Kitchen) talking to any other
 * *individual* staff member, including admin-portal users. One controller
 * shared by all of them: the caller's own identity is read off their staff
 * JWT, never the URL, so a token can only ever act as the user it actually
 * belongs to.
 *
 * Unlike the department-wide channels this replaced, two people holding the
 * same role (e.g. two `bar` accounts) get their own separate thread with
 * everyone else — a channel is a pair of user IDs, not a pair of roles.
 */
class StaffChatController extends Controller
{
    /** The tablet-facing roles that can use this API — the admin dashboard reaches it through its own Livewire page. */
    private const TABLET_ROLES = StaffMessage::ROLES;

    /**
     * GET /staff/chat/channels — one row per other reachable staff member
     * (every other tablet-role account, plus every admin-portal user),
     * newest conversation first.
     */
    public function channels(Request $request): JsonResponse
    {
        $me = $this->myUser($request);
        $contacts = $this->contacts($me);

        $keys = $contacts->map(fn (User $c) => StaffMessage::channelKey($me->id, $c->id));
        $lastByChannel = StaffMessage::whereIn('channel_key', $keys)
            ->latest('created_at')
            ->get()
            ->groupBy('channel_key');

        $channels = $contacts
            ->map(function (User $contact) use ($me, $lastByChannel) {
                $key = StaffMessage::channelKey($me->id, $contact->id);
                $messages = $lastByChannel->get($key, collect());
                $last = $messages->first();
                $role = $this->roleFor($contact);

                return [
                    'user_id' => $contact->id,
                    'name' => $contact->name,
                    'role' => $role,
                    'role_label' => $this->roleLabel($role),
                    'online' => $contact->isRecentlyActive(),
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at?->toIso8601String(),
                    'last_message_label' => optional($last?->created_at)->diffForHumans(['short' => true]),
                    'unread_count' => $messages->where('sender_id', $contact->id)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc(fn (array $c) => $c['last_message_at'] ?? '')
            ->values();

        return response()->json(['data' => $channels]);
    }

    /** GET /staff/chat/channels/{contact}/messages — one thread with a specific person, oldest first. */
    public function messages(Request $request, User $contact): JsonResponse
    {
        $me = $this->myUser($request);
        $this->validateContact($me, $contact);
        $key = StaffMessage::channelKey($me->id, $contact->id);

        $messages = StaffMessage::where('channel_key', $key)->oldest()->get();

        StaffMessage::where('channel_key', $key)
            ->where('sender_id', $contact->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages->map(fn (StaffMessage $m) => $m->toChatArray($me->id))->values()]);
    }

    /** POST /staff/chat/channels/{contact}/messages — send to a specific person. */
    public function send(Request $request, User $contact): JsonResponse
    {
        $me = $this->myUser($request);
        $this->validateContact($me, $contact);

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = StaffMessage::create([
            'channel_key' => StaffMessage::channelKey($me->id, $contact->id),
            'sender_id' => $me->id,
            'recipient_id' => $contact->id,
            'sender_role' => $this->roleFor($me),
            'sender_name' => $me->name,
            'body' => $data['body'],
        ]);

        try {
            broadcast(new StaffChatMessageSent($contact->id, $me->id));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $message->toChatArray($me->id)], 201);
    }

    /** POST /staff/chat/channels/{contact}/typing — a fire-and-forget "I'm typing" signal, never persisted. */
    public function typing(Request $request, User $contact): JsonResponse
    {
        $me = $this->myUser($request);
        $this->validateContact($me, $contact);

        try {
            broadcast(new StaffChatTypingSent(StaffMessage::channelKey($me->id, $contact->id), $me->id));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => true]);
    }

    /**
     * Every other reachable staff member — active, tablet-role or
     * admin-portal, never the caller. Uses `whereHas` rather than Spatie's
     * `role()` scope, which throws `RoleDoesNotExist` if any name in the
     * list hasn't been created yet (a brand-new install, or a test that
     * only seeds a few roles) instead of simply matching nothing.
     */
    private function contacts(User $me): Collection
    {
        return User::where('status', 'active')
            ->where('id', '!=', $me->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_merge(self::TABLET_ROLES, StaffMessage::ADMIN_ROLES)))
            ->orderBy('name')
            ->get();
    }

    /** [$contact] validated as a real, reachable, different-from-caller contact. */
    private function validateContact(User $me, User $contact): void
    {
        abort_if($contact->is($me), 404, 'Unknown contact.');
        abort_unless(
            $contact->status === 'active' && $contact->hasAnyRole(array_merge(self::TABLET_ROLES, StaffMessage::ADMIN_ROLES)),
            404,
            'Unknown contact.'
        );
    }

    /** [$user]'s own department role, or "admin" for an admin-portal user. */
    private function roleFor(User $user): string
    {
        $role = collect(self::TABLET_ROLES)->first(fn (string $r) => $user->hasRole($r));

        return $role ?? 'admin';
    }

    private function roleLabel(string $role): string
    {
        return $role === 'admin' ? 'Manager' : ucfirst($role);
    }

    /** The calling staff member — must hold a tablet role to use this API. */
    private function myUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasAnyRole(self::TABLET_ROLES), 403, 'Not a recognised staff role.');

        return $user;
    }
}
