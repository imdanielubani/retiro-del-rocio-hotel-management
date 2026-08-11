<?php

namespace App\Jobs;

use App\Mail\StayExtensionReceipt;
use App\Models\StayExtensionPayment;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the guest their stay-extension receipt once the Paystack charge is
 * verified.
 *
 * Dispatched synchronously ({@see TabletController::extendStay()} via
 * dispatchSync) rather than after-response: the checkout move re-issues the gate
 * code through its own afterResponse job, and a failure there aborts the whole
 * terminating-callback chain — which was silently dropping this receipt. Running
 * inline keeps delivery independent of that chain and of a queue worker; the send
 * is guarded so a mail failure never breaks the (already committed) extension.
 */
class SendStayExtensionReceipt
{
    use Queueable;

    public function __construct(private int $paymentId) {}

    public function handle(): void
    {
        $payment = StayExtensionPayment::with('booking')->find($this->paymentId);

        // Gone, not actually paid, or the guest left no email to send to.
        if (! $payment || ! $payment->isSuccessful()) {
            return;
        }

        $booking = $payment->booking;
        if (! $booking || ! $booking->customer_email) {
            return;
        }

        try {
            Mail::to($booking->customer_email)->send(new StayExtensionReceipt($booking, $payment));
        } catch (Throwable $e) {
            // A receipt that fails to send must never surface to the guest as a
            // failed extension — the payment and checkout are already committed.
            report($e);
        }
    }
}
