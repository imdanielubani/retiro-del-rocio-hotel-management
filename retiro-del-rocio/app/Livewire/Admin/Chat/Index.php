<?php

namespace App\Livewire\Admin\Chat;

use App\Events\StaffChatMessageSent;
use App\Http\Controllers\Api\V1\StaffChatController;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * Admin → Chat — the admin/manager's own channel with each staff station
 * (Reception, Housekeeping, Maintenance, Security, Bar), sharing the exact
 * same {@see StaffMessage} table every tablet's Chat screen reads and writes.
 */
class Index extends Component
{
    /** The stations the admin dashboard can message. */
    private const ROLES = ['reception', 'housekeeping', 'maintenance', 'security', 'bar'];

    public string $selectedRole = '';

    public string $body = '';

    /**
     * The last message's timestamp seen per role, so a poll can tell a truly
     * new message apart from one already announced. Persists across
     * `wire:poll` requests as ordinary Livewire component state.
     */
    public array $lastSeenAt = [];

    /**
     * False only until the component's first render, so page load never
     * announces every station's existing history as if it just arrived.
     */
    public bool $seeded = false;

    public function selectRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            return;
        }

        $this->selectedRole = $role;
        $this->body = '';
        $this->resetValidation();
    }

    public function send(): void
    {
        if (! in_array($this->selectedRole, self::ROLES, true)) {
            return;
        }

        $data = $this->validate(['body' => ['required', 'string', 'max:1000']]);

        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey('admin', $this->selectedRole),
            'sender_role' => 'admin',
            'sender_name' => Auth::user()?->name,
            'body' => $data['body'],
        ]);

        try {
            broadcast(new StaffChatMessageSent($this->selectedRole, 'admin'));
        } catch (Throwable $e) {
            report($e);
        }

        $this->body = '';
    }

    /**
     * Whether anyone with [$role] has hit a staff-tablet endpoint within the
     * presence window — the same check {@see StaffChatController} uses.
     */
    private function roleOnline(string $role): bool
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->get()
            ->contains(fn (User $u) => $u->isRecentlyActive());
    }

    /**
     * Toasts and chimes any station whose last message is new since the
     * previous poll and wasn't the admin's own send — so posting a reply, or
     * simply opening a thread (which doesn't touch `last_message_at`), never
     * self-notifies. Skipped until [$seeded] so page load doesn't announce
     * every station's existing history at once.
     */
    private function announceNewMessages($channels): void
    {
        foreach ($channels as $channel) {
            $at = optional($channel['last_message_at'])->toIso8601String();
            $previous = $this->lastSeenAt[$channel['role']] ?? null;

            if ($this->seeded && $at && $at !== $previous && $channel['last_sender_role'] !== 'admin') {
                $this->dispatch('chat-message-received');
                $this->dispatch(
                    'toast',
                    type: 'info',
                    message: "New message from {$channel['label']} — ".Str::limit((string) $channel['last_message'], 60),
                );
            }

            if ($at) {
                $this->lastSeenAt[$channel['role']] = $at;
            }
        }

        $this->seeded = true;
    }

    public function render()
    {
        $channels = collect(self::ROLES)
            ->map(function (string $role) {
                $key = StaffMessage::channelKey('admin', $role);
                $messages = StaffMessage::where('channel_key', $key)->latest('created_at')->get();
                $last = $messages->first();

                return [
                    'role' => $role,
                    'label' => ucfirst($role),
                    'online' => $this->roleOnline($role),
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at,
                    'last_sender_role' => $last?->sender_role,
                    'last_message_label' => optional($last?->created_at)->diffForHumans(),
                    'unread_count' => $messages->where('sender_role', $role)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

        $this->announceNewMessages($channels);

        $messages = collect();
        if (in_array($this->selectedRole, self::ROLES, true)) {
            $key = StaffMessage::channelKey('admin', $this->selectedRole);
            $messages = StaffMessage::where('channel_key', $key)->oldest()->get();

            StaffMessage::where('channel_key', $key)
                ->where('sender_role', $this->selectedRole)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('admin.chat.index', [
            'channels' => $channels,
            'messages' => $messages,
        ])->layout('components.admin.app', [
            'title' => 'Chat',
            'subtitle' => 'Message the front desk and every department directly',
        ]);
    }
}
