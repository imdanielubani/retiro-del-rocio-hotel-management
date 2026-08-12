<?php

namespace App\Livewire\Admin\Chat;

use App\Events\StaffChatMessageSent;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * Admin → Chat — the logged-in admin/manager's own channel with each
 * individual staff member (every tablet-role account, plus every other
 * admin-portal user), sharing the exact same {@see StaffMessage} table every
 * tablet's Chat screen reads and writes. Two people holding the same role
 * (e.g. two `bar` waiters) each get their own thread here, not one shared
 * "Bar" bucket.
 */
class Index extends Component
{
    public ?int $selectedContactId = null;

    public string $body = '';

    /**
     * The last message's timestamp seen per contact, so a poll can tell a
     * truly new message apart from one already announced. Persists across
     * `wire:poll` requests as ordinary Livewire component state.
     */
    public array $lastSeenAt = [];

    /**
     * False only until the component's first render, so page load never
     * announces every contact's existing history as if it just arrived.
     */
    public bool $seeded = false;

    public function selectContact(int $contactId): void
    {
        if (! $this->contacts()->contains('id', $contactId)) {
            return;
        }

        $this->selectedContactId = $contactId;
        $this->body = '';
        $this->resetValidation();
    }

    public function send(): void
    {
        $contact = $this->contacts()->firstWhere('id', $this->selectedContactId);
        if (! $contact) {
            return;
        }

        $data = $this->validate(['body' => ['required', 'string', 'max:1000']]);
        $me = Auth::user();

        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey($me->id, $contact->id),
            'sender_id' => $me->id,
            'recipient_id' => $contact->id,
            'sender_role' => 'admin',
            'sender_name' => $me->name,
            'body' => $data['body'],
        ]);

        try {
            broadcast(new StaffChatMessageSent($contact->id, $me->id));
        } catch (Throwable $e) {
            report($e);
        }

        $this->body = '';
    }

    /**
     * Every reachable staff member — active, tablet-role or admin-portal,
     * never the viewer themselves. Uses `whereHas` rather than Spatie's
     * `role()` scope, which throws `RoleDoesNotExist` if any name in the
     * list hasn't been created yet instead of simply matching nothing.
     */
    private function contacts()
    {
        return User::where('status', 'active')
            ->where('id', '!=', Auth::id())
            ->whereHas('roles', fn ($q) => $q->whereIn('name', array_merge(StaffMessage::ROLES, StaffMessage::ADMIN_ROLES)))
            ->orderBy('name')
            ->get();
    }

    private function roleFor(User $contact): string
    {
        $role = collect(StaffMessage::ROLES)->first(fn (string $r) => $contact->hasRole($r));

        return $role ?? 'admin';
    }

    private function roleLabel(User $contact): string
    {
        $role = $this->roleFor($contact);

        return $role === 'admin' ? 'Manager' : ucfirst($role);
    }

    /**
     * Toasts and chimes any contact whose last message is new since the
     * previous poll and wasn't the admin's own send — so posting a reply, or
     * simply opening a thread (which doesn't touch `last_message_at`), never
     * self-notifies. Skipped until [$seeded] so page load doesn't announce
     * every contact's existing history at once.
     */
    private function announceNewMessages($channels): void
    {
        foreach ($channels as $channel) {
            $at = optional($channel['last_message_at'])->toIso8601String();
            $previous = $this->lastSeenAt[$channel['user_id']] ?? null;

            if ($this->seeded && $at && $at !== $previous && ! $channel['last_message_mine']) {
                $this->dispatch('chat-message-received');
                $this->dispatch(
                    'toast',
                    type: 'info',
                    message: "New message from {$channel['name']} — ".Str::limit((string) $channel['last_message'], 60),
                );
            }

            if ($at) {
                $this->lastSeenAt[$channel['user_id']] = $at;
            }
        }

        $this->seeded = true;
    }

    public function render()
    {
        $me = Auth::user();
        $contacts = $this->contacts();

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

                return [
                    'user_id' => $contact->id,
                    'name' => $contact->name,
                    'role' => $this->roleFor($contact),
                    'role_label' => $this->roleLabel($contact),
                    'online' => $contact->isRecentlyActive(),
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at,
                    'last_message_mine' => $last?->sender_id === $me->id,
                    'last_message_label' => optional($last?->created_at)->diffForHumans(),
                    'unread_count' => $messages->where('sender_id', $contact->id)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

        $this->announceNewMessages($channels);

        $messages = collect();
        $selected = $contacts->firstWhere('id', $this->selectedContactId);
        if ($selected) {
            $key = StaffMessage::channelKey($me->id, $selected->id);
            $messages = StaffMessage::where('channel_key', $key)->oldest()->get();

            StaffMessage::where('channel_key', $key)
                ->where('sender_id', $selected->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('admin.chat.index', [
            'channels' => $channels,
            'messages' => $messages,
            'selected' => $selected,
        ])->layout('components.admin.app', [
            'title' => 'Chat',
            'subtitle' => 'Message the front desk and every staff member directly',
        ]);
    }
}
