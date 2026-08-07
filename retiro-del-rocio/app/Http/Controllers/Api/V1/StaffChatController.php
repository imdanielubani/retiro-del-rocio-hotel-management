<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StaffChatTypingSent;
use App\Http\Controllers\Controller;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Internal staff chat — every staff tablet's Chat screen (Reception,
 * Housekeeping, Maintenance, Security) talking to any other station,
 * including the Admin dashboard. One controller shared by all of them: the
 * caller's own role is read off their staff JWT, never the URL, so a token
 * can only ever act as the role it actually holds.
 */
class StaffChatController extends Controller
{
    /** The tablet-facing roles — the admin dashboard reaches this data through its own Livewire page, not this API. */
    private const TABLET_ROLES = ['reception', 'housekeeping', 'maintenance', 'security'];

    /**
     * GET /staff/chat/channels — one row per other station (the three peer
     * tablets, plus Admin), newest conversation first.
     */
    public function channels(Request $request): JsonResponse
    {
        $me = $this->myRole($request);
        $others = array_values(array_diff(StaffMessage::ROLES, [$me]));

        $keys = array_map(fn (string $other) => StaffMessage::channelKey($me, $other), $others);
        $lastByChannel = StaffMessage::whereIn('channel_key', $keys)
            ->latest('created_at')
            ->get()
            ->groupBy('channel_key');

        $channels = collect($others)
            ->map(function (string $other) use ($me, $lastByChannel) {
                $key = StaffMessage::channelKey($me, $other);
                $messages = $lastByChannel->get($key, collect());
                $last = $messages->first();

                return [
                    'role' => $other,
                    'label' => $this->labelFor($other),
                    'online' => $this->roleOnline($other),
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at?->toIso8601String(),
                    'last_message_label' => optional($last?->created_at)->diffForHumans(['short' => true]),
                    'unread_count' => $messages->where('sender_role', $other)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc(fn (array $c) => $c['last_message_at'] ?? '')
            ->values();

        return response()->json(['data' => $channels]);
    }

    /** GET /staff/chat/channels/{role}/messages — one channel's thread, oldest first. */
    public function messages(Request $request, string $role): JsonResponse
    {
        $me = $this->myRole($request);
        $other = $this->otherRole($me, $role);
        $key = StaffMessage::channelKey($me, $other);

        $messages = StaffMessage::where('channel_key', $key)->oldest()->get();

        StaffMessage::where('channel_key', $key)
            ->where('sender_role', $other)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages->map(fn (StaffMessage $m) => $m->toChatArray($me))->values()]);
    }

    /** POST /staff/chat/channels/{role}/messages — send to another station. */
    public function send(Request $request, string $role): JsonResponse
    {
        [$user, $me] = $this->myRoleWithUser($request);
        $other = $this->otherRole($me, $role);

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = StaffMessage::create([
            'channel_key' => StaffMessage::channelKey($me, $other),
            'sender_role' => $me,
            'sender_name' => $user->name,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $message->toChatArray($me)], 201);
    }

    /** POST /staff/chat/channels/{role}/typing — a fire-and-forget "I'm typing" signal, never persisted. */
    public function typing(Request $request, string $role): JsonResponse
    {
        $me = $this->myRole($request);
        $other = $this->otherRole($me, $role);

        try {
            broadcast(new StaffChatTypingSent(StaffMessage::channelKey($me, $other), $me));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => true]);
    }

    private function labelFor(string $role): string
    {
        return $role === 'admin' ? 'Admin' : ucfirst($role);
    }

    /** Whether anyone with [$role] has hit an endpoint within the presence window. */
    private function roleOnline(string $role): bool
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->get()
            ->contains(fn (User $u) => $u->isRecentlyActive());
    }

    /** [$role] validated as a real, different-from-caller channel partner. */
    private function otherRole(string $me, string $role): string
    {
        abort_unless(in_array($role, StaffMessage::ROLES, true) && $role !== $me, 404, 'Unknown channel.');

        return $role;
    }

    /** The calling staff member's own role, which must be one of the tablet roles. */
    private function myRole(Request $request): string
    {
        return $this->myRoleWithUser($request)[1];
    }

    /** @return array{0: User, 1: string} */
    private function myRoleWithUser(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $role = collect(self::TABLET_ROLES)->first(fn (string $r) => $user->hasRole($r));
        abort_unless($role !== null, 403, 'Not a recognised staff role.');

        return [$user, $role];
    }
}
