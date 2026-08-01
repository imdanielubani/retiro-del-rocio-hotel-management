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
     * in/out, cancelled) — or its checkout date moves, e.g. a paid stay
     * extension — so the front desk's already-open dashboard sees it the
     * moment it happens rather than waiting on its own next poll. A stay
     * extension never changes `status` (the guest stays checked_in
     * throughout), so `check_out` has to be watched on its own; otherwise the
     * departures list keeps showing the guest under their old, pre-extension
     * checkout date until something else happens to trigger a refetch.
     */
    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status') || $booking->wasChanged('check_out')) {
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
