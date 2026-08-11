<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reverses a Paystack charge. Website bookings store the Paystack transaction
 * reference in {@see Booking::$reference} and the channel in `payment_method`, so
 * an online payment can be refunded straight back to the guest's card/bank via
 * Paystack's /refund endpoint. Manual (front-desk) bookings have no Paystack
 * transaction to reverse and must be refunded by bank transfer instead.
 */
class PaystackRefundService
{
    /** Channels that are not an online Paystack charge and so can't be reversed. */
    private const NON_ONLINE_METHODS = ['', 'manual', 'cash', 'bank_transfer'];

    /**
     * Submit a refund to Paystack for [$amountNaira] of this booking's charge.
     *
     * @return array{ok: bool, message: string}
     */
    public function refund(Booking $booking, int $amountNaira): array
    {
        $secret = config('services.paystack.secret_key');
        if (blank($secret)) {
            return ['ok' => false, 'message' => 'Online refunds are unavailable — Paystack is not configured.'];
        }

        if (! $this->isOnlineCharge($booking)) {
            return ['ok' => false, 'message' => 'This booking was not paid online, so it cannot be reversed on Paystack. Refund it by bank transfer instead.'];
        }

        try {
            $response = Http::withToken($secret)->acceptJson()->post(
                rtrim((string) config('services.paystack.payment_url'), '/').'/refund',
                [
                    'transaction' => $booking->reference,
                    // Paystack works in kobo; omitting amount refunds in full.
                    'amount' => max(0, $amountNaira) * 100,
                    'merchant_note' => 'Refund for '.$booking->cancellationCode(),
                ],
            );
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Could not reach Paystack. Please try again.'];
        }

        if (! $response->ok() || data_get($response->json(), 'status') !== true) {
            $message = data_get($response->json(), 'message') ?: 'Paystack declined the refund.';

            return ['ok' => false, 'message' => 'Paystack: '.$message];
        }

        return ['ok' => true, 'message' => 'Refund submitted to Paystack — it will settle to the guest shortly.'];
    }

    /** True when the booking was paid through Paystack (has a reversible charge). */
    public function isOnlineCharge(Booking $booking): bool
    {
        return filled($booking->reference)
            && ! in_array((string) $booking->payment_method, self::NON_ONLINE_METHODS, true);
    }
}
