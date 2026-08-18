<?php

namespace App\Livewire\Admin\Notifications;

use App\Notifications\BookingReceived;
use App\Notifications\CinemaBookingReceived;
use App\Notifications\GymMembershipReceived;
use App\Notifications\MessageReceived;
use App\Notifications\RestaurantReservationReceived;
use App\Notifications\SpaBookingReceived;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Full Notifications page — every DatabaseNotification the admin bell
 * (App\Livewire\Admin\Notifications\Bell) surfaces a preview of, but as a
 * searchable, filterable, paginated list rather than a 5-item dropdown. Reads
 * the same `notifications` table via the Notifiable trait on User; nothing
 * new is written to storage here.
 */
class Index extends Component
{
    use WithPagination;

    public string $typeFilter = '';

    public string $statusFilter = '';

    /** @var array<string, array{label: string, route: string, bg: string, fg: string, icon: string}> */
    protected const TYPE_META = [
        MessageReceived::class => ['label' => 'Message', 'route' => 'admin.messages.index', 'bg' => '#f3f3ee', 'fg' => '#6b7280', 'icon' => 'message'],
        BookingReceived::class => ['label' => 'Booking', 'route' => 'admin.bookings.index', 'bg' => '#e7f6ec', 'fg' => '#16a34a', 'icon' => 'check'],
        SpaBookingReceived::class => ['label' => 'Spa & Wellness', 'route' => 'admin.spa.bookings', 'bg' => '#f3e8ff', 'fg' => '#7c3aed', 'icon' => 'spa'],
        GymMembershipReceived::class => ['label' => 'Gym & Fitness', 'route' => 'admin.gym.memberships', 'bg' => '#e7f6ec', 'fg' => '#16a34a', 'icon' => 'gym'],
        RestaurantReservationReceived::class => ['label' => 'Restaurant', 'route' => 'admin.restaurant.reservations', 'bg' => '#fff1e0', 'fg' => '#f38c00', 'icon' => 'restaurant'],
        CinemaBookingReceived::class => ['label' => 'Cinema', 'route' => 'admin.cinema.bookings', 'bg' => '#fef9c3', 'fg' => '#a16207', 'icon' => 'cinema'],
    ];

    public function updating($name): void
    {
        if (in_array($name, ['typeFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function meta(string $type): array
    {
        return self::TYPE_META[$type] ?? ['label' => 'Notification', 'route' => 'admin.dashboard', 'bg' => '#f3f3ee', 'fg' => '#6b7280', 'icon' => 'bell'];
    }

    public function url(DatabaseNotification $n): string
    {
        $data = $n->data ?? [];
        if (! empty($data['url'])) {
            return $data['url'];
        }

        $route = $this->meta($n->type)['route'];

        return Route::has($route) ? route($route) : route('admin.dashboard');
    }

    public function markRead(string $id): void
    {
        auth()->user()?->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markUnread(string $id): void
    {
        auth()->user()?->notifications()->whereKey($id)->first()?->markAsUnread();
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
        $this->dispatch('toast', type: 'success', message: 'All notifications marked as read.');
    }

    public function render()
    {
        $user = auth()->user();

        $notifications = $user
            ? $user->notifications()
                ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
                ->when($this->statusFilter === 'unread', fn ($q) => $q->whereNull('read_at'))
                ->when($this->statusFilter === 'read', fn ($q) => $q->whereNotNull('read_at'))
                ->latest()
                ->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'types' => self::TYPE_META,
            'unreadCount' => $user?->unreadNotifications()->count() ?? 0,
        ])->layout('components.admin.app', [
            'title' => 'Notifications',
            'subtitle' => 'Every booking, message and payment alert in one place',
        ]);
    }
}
