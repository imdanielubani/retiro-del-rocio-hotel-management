<?php

namespace App\Listeners;

use App\Events\BookingDatesChanged;
use App\Jobs\GenerateTtlockAccess;
use App\Jobs\UpdateTtlockAccess;
use App\Services\TTLockService;

/** On a date change, re-issue the passcode for the new window. */
class ReissueTtlockAccessOnDateChange
{
    public function handle(BookingDatesChanged $event): void
    {
        $booking = $event->booking;

        if (! app(TTLockService::class)->isConfigured() || ! $booking->lockId()) {
            return;
        }

        if ($booking->keyboard_pwd_id) {
            // Delete the old code, then mint a fresh one for the new dates.
            UpdateTtlockAccess::dispatchAfterResponse($booking);
        } else {
            // No code yet (e.g. was never provisioned) — just generate one.
            GenerateTtlockAccess::dispatchAfterResponse($booking);
        }
    }
}
