<?php

namespace App\Observers;

use App\Events\BookingDatesChanged;
use App\Models\Booking;

class BookingObserver
{
    /**
     * Re-issue TTLock access whenever an active booking's stay dates change,
     * regardless of which screen made the edit. The passcode-provisioning
     * writes don't touch check_in/check_out, so this never loops.
     */
    public function updated(Booking $booking): void
    {
        if (! in_array($booking->status, ['paid', 'checked_in'], true)) {
            return;
        }

        if ($booking->wasChanged('check_in') || $booking->wasChanged('check_out')) {
            BookingDatesChanged::dispatch($booking);
        }
    }
}
