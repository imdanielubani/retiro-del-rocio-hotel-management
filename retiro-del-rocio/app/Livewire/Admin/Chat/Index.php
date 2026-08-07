<?php

namespace App\Livewire\Admin\Chat;

use App\Http\Controllers\Api\V1\StaffChatController;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Admin → Chat — the admin/manager's own channel with each staff station
 * (Reception, Housekeeping, Maintenance, Security), sharing the exact same
 * {@see StaffMessage} table every tablet's Chat screen reads and writes.
 */
class Index extends Component
{
    /** The stations the admin dashboard can message. */
    private const ROLES = ['reception', 'housekeeping', 'maintenance', 'security'];

    public string $selectedRole = '';

    public string $body = '';

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
                    'last_message_label' => optional($last?->created_at)->diffForHumans(),
                    'unread_count' => $messages->where('sender_role', $role)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

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
