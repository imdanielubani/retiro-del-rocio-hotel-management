<?php

namespace App\Jobs;

use App\Mail\StayExtensionReceipt;
use App\Models\StayExtensionPayment;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the guest their stay-extension receipt once the Paystack charge is
 * verified — off the tablet's request path.
 *
 * Sending is an SMTP hand-off that would otherwise make the guest wait on the
 * "Pay" tap, so the controller stamps the payment and hands the email to this
 * job, dispatched after the response has been sent.
 *
 * Deliberately not a queued job (like {@see ProvisionVisitorPass}): it runs with
 * ->afterResponse() so a receipt still goes out without a worker being up.
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
