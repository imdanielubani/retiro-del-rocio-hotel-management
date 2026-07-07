<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\TTLockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes a booking's TTLock passcode (and official QR code, if any),
 * immediately disabling door access. Used on cancellation and before
 * re-issuing a code on date changes.
 */
class RevokeTtlockAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int, array{lockId:string, keyboardPwdId:string, qrCodeId:?string}>  $grants
     *         One entry per gate lock this booking holds access on.
     */
    public function __construct(
        public int $bookingId,
        public array $grants,
    ) {}

    public function handle(TTLockService $ttlock): void
    {
        foreach ($this->grants as $grant) {
            $lockId = $grant['lockId'] ?? null;
            $keyboardPwdId = $grant['keyboardPwdId'] ?? null;
            if (! $lockId || ! $keyboardPwdId) {
                continue;
            }

            try {
                $ttlock->deletePasscode($lockId, $keyboardPwdId);
            } catch (Throwable $e) {
                // Log and continue to the next gate — one offline gate must not
                // block revoking access on the others.
                Log::warning('TTLock passcode delete failed for booking '.$this->bookingId.' (lock '.$lockId.'): '.$e->getMessage());
            }

            // Best-effort QR removal — never let it block passcode revocation.
            if (! empty($grant['qrCodeId'])) {
                try {
                    $ttlock->deleteQrCode($lockId, $grant['qrCodeId']);
                } catch (Throwable $e) {
                    Log::warning('TTLock QR delete failed for booking '.$this->bookingId.' (lock '.$lockId.'): '.$e->getMessage());
                }
            }
        }

        Booking::whereKey($this->bookingId)->update([
            'passcode' => null,
            'keyboard_pwd_id' => null,
            'qr_code_id' => null,
            'qr_code_link' => null,
            'ttlock_grants' => null,
            'ttlock_status' => 'deleted',
            'ttlock_error' => null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('RevokeTtlockAccess failed for booking '.$this->bookingId.': '.$e->getMessage());

        Booking::whereKey($this->bookingId)->update(['ttlock_error' => $e->getMessage()]);
    }
}
