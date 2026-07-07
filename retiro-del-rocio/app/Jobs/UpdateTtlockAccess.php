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
 * Re-issues access after a booking's dates change: deletes the old passcode
 * then generates a fresh one (with a new QR + updated email) for the new window.
 */
class UpdateTtlockAccess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public Booking $booking) {}

    public function handle(TTLockService $ttlock): void
    {
        $booking = $this->booking->fresh();

        if (! $booking || in_array($booking->status, ['cancelled', 'checked_out'], true)) {
            return;
        }

        // Remove the previous passcode (and QR) on every gate so we can mint
        // fresh ones for the new dates.
        foreach ($booking->revokableGrants() as $grant) {
            try {
                $ttlock->deletePasscode($grant['lockId'], $grant['keyboardPwdId']);
                if (! empty($grant['qrCodeId'])) {
                    $ttlock->deleteQrCode($grant['lockId'], $grant['qrCodeId']);
                }
            } catch (Throwable $e) {
                // Log but continue — a fresh code is more important than a clean delete.
                Log::warning('UpdateTtlockAccess: could not delete old access for booking '.$booking->id.' (lock '.$grant['lockId'].'): '.$e->getMessage());
            }
        }

        $booking->forceFill([
            'passcode' => null,
            'keyboard_pwd_id' => null,
            'qr_code_id' => null,
            'qr_code_link' => null,
            'ttlock_grants' => null,
        ])->save();

        // Generate the replacement code + QR + updated email, in-process.
        GenerateTtlockAccess::dispatchSync($booking, isUpdate: true);
    }

    public function failed(Throwable $e): void
    {
        Log::error('UpdateTtlockAccess failed for booking '.$this->booking->id.': '.$e->getMessage());

        Booking::whereKey($this->booking->id)->update([
            'ttlock_status' => 'failed',
            'ttlock_error' => $e->getMessage(),
        ]);
    }
}
