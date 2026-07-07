<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Jobs\GenerateTtlockAccess;
use App\Services\TTLockService;

/** On confirmation, immediately provision passcode + QR + email. */
class ProvisionTtlockAccess
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        // Only skip when TTLock isn't set up at all. Once configured we always
        // attempt, so the result — success or the exact reason it couldn't —
        // is recorded and visible in the TTLock module.
        if (! app(TTLockService::class)->isConfigured()) {
            return;
        }

        // Already provisioned? Don't duplicate.
        if ($booking->keyboard_pwd_id && $booking->ttlock_status === 'active') {
            return;
        }

        // Runs right after the HTTP response is sent — immediate, and with no
        // dependency on a running queue worker.
        GenerateTtlockAccess::dispatchAfterResponse($booking);
    }
}
