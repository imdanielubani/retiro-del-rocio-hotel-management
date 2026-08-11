<?php

namespace App\Jobs;

use App\Models\VisitorPass;
use App\Services\VisitorPassProvisioner;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pushes a visitor's one-time online code onto every gate lock and emails it to
 * them — off the tablet's request path.
 *
 * Issuing a pass must feel instant. Talking to TTLock means one HTTP round trip
 * per gate (each with its own timeout and retries) and then an SMTP hand-off;
 * together that easily outlives the tablet's read timeout, which the guest saw as
 * a bogus "couldn't create the pass" even though the pass had been created. So
 * the controller commits the pass on its offline code and hands the slow work to
 * this job, dispatched after the response has been sent.
 *
 * Deliberately not a queued job: it must run without a worker being up, since a
 * visitor pass is useless an hour later. It is dispatched with ->afterResponse().
 */
class ProvisionVisitorPass
{
    use Queueable;

    public function __construct(private int $passId) {}

    public function handle(VisitorPassProvisioner $provisioner): void
    {
        $pass = VisitorPass::find($this->passId);

        // Cancelled or denied while we were queued — nothing to hand out.
        if (! $pass || ! $pass->isOpen()) {
            return;
        }

        try {
            $provisioner->provision($pass);
        } catch (Throwable $e) {
            // provision() already swallows TTLock errors; this is the last resort
            // so a surprise never leaves the pass stuck on 'pending'.
            $pass->forceFill([
                'ttlock_status' => 'failed',
                'ttlock_error' => $e->getMessage(),
            ])->save();
        }

        $provisioner->email($pass->fresh());
    }
}
