<?php

namespace App\Livewire\Admin\Notifications;

use App\Notifications\BookingReceived;
use App\Notifications\CinemaBookingReceived;
use App\Notifications\GymMembershipReceived;
use App\Notifications\MessageReceived;
use App\Notifications\RestaurantReservationReceived;
use App\Notifications\SpaBookingReceived;
use Livewire\Component;

class Bell extends Component
{
    public function mount(): void
    {
        // Pop anything that landed while the admin was away (e.g. a gym payment
        // made on the public site) as soon as they open an admin page.
        $this->popUnseen();
    }

    /** Mark all notifications read once the admin opens the bell. */
    public function markRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    /**
     * Fire a popup toast for any recent UNREAD notification not yet popped this
     * session — covers new room/spa/gym bookings & payments and future services.
     * Called on mount and on each poll, so it works whether the admin was on the
     * page when it arrived or opens the dashboard afterwards.
     */
    public function popUnseen(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $popped = session('bell_popped', []);

        $new = $user->unreadNotifications()
            ->whereNotIn('id', $popped)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get();

        if ($new->isEmpty()) {
            return;
        }

        // Remember what we've popped (capped) so navigation/polls don't repeat it.
        session(['bell_popped' => array_slice(array_merge($popped, $new->pluck('id')->all()), -50)]);

        if ($new->count() === 1) {
            $this->dispatch('toast', type: 'info', message: $new->first()->data['message'] ?? 'New activity received.');
        } else {
            $this->dispatch('toast', type: 'info', message: $new->count().' new bookings & payments received.');
        }
    }

    public function render()
    {
        $user = auth()->user();

        $messageNotifs = $user
            ? $user->notifications()->where('type', MessageReceived::class)->latest()->take(5)->get()
            : collect();
        $bookingNotifs = $user
            ? $user->notifications()->where('type', BookingReceived::class)->latest()->take(6)->get()
            : collect();
        $spaNotifs = $user
            ? $user->notifications()->where('type', SpaBookingReceived::class)->latest()->take(5)->get()
            : collect();
        $gymNotifs = $user
            ? $user->notifications()->where('type', GymMembershipReceived::class)->latest()->take(5)->get()
            : collect();
        $restoNotifs = $user
            ? $user->notifications()->where('type', RestaurantReservationReceived::class)->latest()->take(5)->get()
            : collect();
        $cinemaNotifs = $user
            ? $user->notifications()->where('type', CinemaBookingReceived::class)->latest()->take(5)->get()
            : collect();
        $notifCount = $user ? $user->unreadNotifications()->count() : 0;

        return view('admin.notifications.bell', compact(
            'messageNotifs',
            'bookingNotifs',
            'spaNotifs',
            'gymNotifs',
            'restoNotifs',
            'cinemaNotifs',
            'notifCount',
        ));
    }
}
