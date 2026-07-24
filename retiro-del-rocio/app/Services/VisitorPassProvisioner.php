<?php

namespace App\Services;

use App\Mail\VisitorPassMail;
use App\Models\VisitorPass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Issues and tears down a visitor's smart-lock access.
 *
 * Every pass carries a manual OFFLINE code (keyed by security at the gate). When
 * TTLock is reachable, this also mints a one-time ONLINE passcode on the gate
 * lock that the visitor can use themselves. It never throws on a TTLock failure —
 * the offline code always works, so issuing a pass can never be blocked by a lock
 * being offline.
 */
class VisitorPassProvisioner
{
    public function __construct(private TTLockService $ttlock) {}

    /**
     * Mint the online (TTLock) code alongside the offline code already on the
     * pass. Records why it couldn't, so the register can show "offline" vs a real
     * failure — never surfaces an exception to the caller.
     */
    public function provision(VisitorPass $pass): void
    {
        $lockIds = $this->gateLockIds();

        if (! $this->ttlock->isConfigured() || empty($lockIds)) {
            $pass->forceFill(['ttlock_status' => 'offline'])->save();

            return;
        }

        $hours = max(1, (int) config('services.ttlock.visitor_window_hours', 12));
        $start = Carbon::now();
        $end = Carbon::now()->addHours($hours);

        // ONE code, distinct from the offline one, pushed to every gate lock.
        $sharedCode = $pass->online_code ?: VisitorPass::generateUniqueCode([$pass->code]);

        $grants = [];
        $errors = [];
        foreach ($lockIds as $lockId) {
            try {
                $result = $this->ttlock->createPasscode(
                    $lockId,
                    $start,
                    $end,
                    'Visitor '.$pass->visitor_name,
                    $sharedCode,
                );
                $grants[] = ['lockId' => $lockId, 'keyboardPwdId' => (string) $result['keyboardPwdId']];
            } catch (Throwable $e) {
                $errors[] = $lockId.': '.$e->getMessage();
                Log::warning('Visitor pass TTLock provisioning failed for #'.$pass->id.' (lock '.$lockId.'): '.$e->getMessage());
            }
        }

        if (empty($grants)) {
            $pass->forceFill([
                'ttlock_status' => 'failed',
                'ttlock_error' => implode(' · ', $errors),
            ])->save();

            return;
        }

        $pass->forceFill([
            'online_code' => $sharedCode,
            'ttlock_grants' => $grants,
            // Mirror the first gate into the legacy columns for back-compat.
            'keyboard_pwd_id' => $grants[0]['keyboardPwdId'],
            'lock_id' => $grants[0]['lockId'],
            'ttlock_status' => 'active',
            'ttlock_error' => $errors ? implode(' · ', $errors) : null,
            'expires_at' => $end,
        ])->save();
    }

    /** Email the visitor their code(s). Best-effort — never blocks issuing. */
    public function email(VisitorPass $pass): void
    {
        if (! $pass->visitor_email) {
            return;
        }

        try {
            Mail::to($pass->visitor_email)->send(new VisitorPassMail($pass->fresh()));
        } catch (Throwable $e) {
            Log::warning('Visitor pass email failed for #'.$pass->id.': '.$e->getMessage());
        }
    }

    /**
     * Remove the online code from EVERY gate lock once the pass is spent or
     * revoked, so a one-time code is truly one-time and can't be reused at the
     * other gate.
     */
    public function revoke(VisitorPass $pass): void
    {
        foreach ($pass->revokableGrants() as $grant) {
            try {
                $this->ttlock->deletePasscode($grant['lockId'], $grant['keyboardPwdId']);
            } catch (Throwable $e) {
                Log::warning('Visitor pass TTLock delete failed for #'.$pass->id.' (lock '.$grant['lockId'].'): '.$e->getMessage());
            }
        }
    }

    /**
     * Close out a departing guest's outstanding visitor passes.
     *
     * A pass belongs to a stay, not to a room. Once the host has checked out
     * there is nobody for their visitor to be visiting, so leaving the codes live
     * would let a stranger through the gate on the credentials of someone who is
     * no longer in the building. Verified visitors are left alone — they are
     * already inside, and the register still needs to show them.
     *
     * @return int passes closed
     */
    public function closeOutBooking(int $bookingId): int
    {
        $passes = VisitorPass::open()->where('booking_id', $bookingId)->get();

        foreach ($passes as $pass) {
            $this->revoke($pass);
            $pass->forceFill([
                'status' => VisitorPass::CANCELLED,
                'cancelled_at' => now(),
                'ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status,
            ])->save();
        }

        return $passes->count();
    }

    /**
     * Every gate lock a visitor passcode is issued against. TTLOCK_LOCK_ID may be
     * a comma-separated list; the same code opens all of them.
     *
     * @return string[]
     */
    public function gateLockIds(): array
    {
        return collect(explode(',', (string) config('services.ttlock.lock_id')))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** The primary gate lock (back-compat for single-gate callers). */
    public function gateLockId(): ?string
    {
        return $this->gateLockIds()[0] ?? null;
    }
}
