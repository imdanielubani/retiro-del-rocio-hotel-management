<?php

namespace App\Observers;

use App\Events\BookingChanged;
use App\Events\BookingDatesChanged;
use App\Models\Booking;

class BookingObserver
{
    /**
     * A brand-new reservation — from the website, the admin, or anywhere — is
     * signalled to the reception tablet so its guest, booking and dashboard
     * lists update live rather than on their next poll.
     */
    public function created(Booking $booking): void
    {
        BookingChanged::announce($booking);
    }

    /**
     * Re-issue TTLock access whenever an active booking's stay dates change,
     * regardless of which screen made the edit. The passcode-provisioning
     * writes don't touch check_in/check_out, so this never loops.
     *
     * Also signal reception whenever the booking moves state (confirmed, checked
     * in/out, cancelled) so the front desk sees it the moment it happens.
     */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status')) {
            BookingChanged::announce($booking);
        }

        if (! in_array($booking->status, ['paid', 'checked_in'], true)) {
            return;
        }

        if ($booking->wasChanged('check_in') || $booking->wasChanged('check_out')) {
            BookingDatesChanged::dispatch($booking);
        }
    }
}
