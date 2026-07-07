<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Jobs\RevokeTtlockAccess;

/** On cancellation, queue deletion of the guest's passcode. */
class RevokeTtlockAccessOnCancel
{
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        // Nothing to revoke if no passcode was ever issued on any gate.
        $grants = $booking->revokableGrants();
        if (empty($grants)) {
            return;
        }

        RevokeTtlockAccess::dispatchAfterResponse($booking->id, $grants);
    }
}
