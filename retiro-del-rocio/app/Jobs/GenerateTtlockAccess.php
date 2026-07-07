<?php

namespace App\Jobs;

use App\Mail\TtlockAccessMail;
use App\Models\Booking;
use App\Services\TTLockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Provisions TTLock guest access for a confirmed booking: generates a
 * time-bound passcode, an official TTLock QR code (when the lock supports it)
 * and emails both to the guest. Safe to re-run — an existing passcode is
 * reused rather than duplicated.
 *
 * Runs immediately (dispatched after response) but is also queue-capable,
 * so retries/backoff apply when a worker is present.
 */
class GenerateTtlockAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public Booking $booking, public bool $isUpdate = false) {}

    public function handle(TTLockService $ttlock): void
    {
        $booking = $this->booking->fresh();

        if (! $booking || in_array($booking->status, ['cancelled', 'checked_out'], true)) {
            return; // Nothing to provision.
        }

        // One or more Access Gate locks (from .env, comma-separated). No per-room mapping.
        $lockIds = $booking->lockIds();
        if (empty($lockIds)) {
            $this->markFailed($booking, 'No Access Gate lock configured. Set TTLOCK_LOCK_ID in the .env file, then retry.');

            return;
        }

        $booking->forceFill(['ttlock_status' => 'pending', 'ttlock_error' => null])->save();

        [$start, $end] = $booking->accessWindow();

        // One shared passcode for every gate: reuse the existing code on retries,
        // otherwise mint a fresh 6-digit code once.
        $sharedCode = $booking->passcode ?: (string) random_int(100000, 999999);
        $passcodeCreatedThisRun = false;

        // Start from any grants we already secured (so a retry only fills the gaps).
        $grants = collect($booking->ttlock_grants ?: [])->keyBy('lockId');
        $errors = [];

        foreach ($lockIds as $i => $lockId) {
            $gateName = count($lockIds) > 1 ? 'Gate '.($i + 1) : 'Access gate';
            $existing = $grants->get($lockId, []);

            // Skip a gate that is already fully provisioned.
            if (($existing['status'] ?? null) === 'active' && ! empty($existing['keyboardPwdId'])) {
                continue;
            }

            try {
                // Passcode: reuse the shared digits on every gate.
                $keyboardPwdId = $existing['keyboardPwdId'] ?? null;
                if (! $keyboardPwdId) {
                    $result = $ttlock->createPasscode($lockId, $start, $end, $booking->customer_name, $sharedCode);
                    $keyboardPwdId = (string) $result['keyboardPwdId'];
                    $passcodeCreatedThisRun = true;
                }

                $grants->put($lockId, [
                    'lockId' => $lockId,
                    'name' => $gateName,
                    'keyboardPwdId' => $keyboardPwdId,
                    'status' => 'active',
                    'error' => null,
                ]);
            } catch (Throwable $e) {
                $grants->put($lockId, [
                    'lockId' => $lockId,
                    'name' => $gateName,
                    'keyboardPwdId' => $existing['keyboardPwdId'] ?? null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                $errors[] = $gateName.': '.$e->getMessage();
                Log::warning('TTLock gate provisioning failed for booking '.$booking->id.' ('.$gateName.'): '.$e->getMessage());
            }
        }

        // Persist grants ordered like the configured gate list.
        $orderedGrants = collect($lockIds)->map(fn ($id) => $grants->get($id))->filter()->values()->all();
        $activeGrants = collect($orderedGrants)->where('status', 'active');
        $primary = $activeGrants->first() ?: ($orderedGrants[0] ?? null);

        $booking->forceFill([
            'passcode' => $activeGrants->isNotEmpty() ? $sharedCode : $booking->passcode,
            'ttlock_grants' => $orderedGrants,
            // Mirror the primary active gate into the legacy passcode column for
            // existing single-gate checks. QR is no longer generated.
            'keyboard_pwd_id' => $primary['keyboardPwdId'] ?? $booking->keyboard_pwd_id,
            'qr_code_id' => null,
            'qr_code_link' => null,
            'ttlock_status' => $errors ? 'failed' : 'active',
            'ttlock_error' => $errors ? implode(' · ', $errors) : null,
        ])->save();

        // Email the guest their shared passcode (+ QR links) once — on first
        // provisioning or a date-change re-issue, not on a pure retry of a
        // gate that failed earlier.
        if ($booking->customer_email && $activeGrants->isNotEmpty() && ($passcodeCreatedThisRun || $this->isUpdate)) {
            Mail::to($booking->customer_email)->send(new TtlockAccessMail($booking, $this->isUpdate));
        }

        // If every gate failed, throw so a queue worker (when present) retries.
        if ($activeGrants->isEmpty()) {
            throw new \RuntimeException('TTLock: no gate could be provisioned — '.implode(' · ', $errors));
        }
    }

    protected function markFailed(Booking $booking, string $message): void
    {
        $booking->forceFill(['ttlock_status' => 'failed', 'ttlock_error' => $message])->save();
        Log::warning('TTLock access not provisioned for booking '.$booking->id.': '.$message);
    }

    public function failed(Throwable $e): void
    {
        Log::error('GenerateTtlockAccess failed for booking '.$this->booking->id.': '.$e->getMessage());

        Booking::whereKey($this->booking->id)->update([
            'ttlock_status' => 'failed',
            'ttlock_error' => $e->getMessage(),
        ]);
    }
}
