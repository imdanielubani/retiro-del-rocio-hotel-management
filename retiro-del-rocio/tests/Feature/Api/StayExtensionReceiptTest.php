<?php

namespace Tests\Feature\Api;

use App\Jobs\SendStayExtensionReceipt;
use App\Mail\StayExtensionReceipt;
use App\Models\Booking;
use App\Models\StayExtensionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The stay-extension receipt email a guest gets after paying to extend.
 */
class StayExtensionReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Booking, 1: StayExtensionPayment} */
    private function make(?string $email = 'guest@example.test', string $status = StayExtensionPayment::SUCCESS): array
    {
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => $email,
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'nights' => 7,
            'guests' => 2,
            'amount' => 52500,
            'status' => 'checked_in',
        ]);

        $payment = StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-ABCDEFGHIJ',
            'nights' => 2,
            'new_check_out' => now()->addDays(6)->toDateString(),
            'amount' => 16125, // 15,000 + 7.5% VAT
            'vat' => 1125,
            'status' => $status,
            'paid_at' => $status === StayExtensionPayment::SUCCESS ? now() : null,
            'payment_method' => 'card',
        ]);

        return [$booking, $payment];
    }

    public function test_it_emails_the_guest_their_receipt(): void
    {
        Mail::fake();
        [, $payment] = $this->make();

        SendStayExtensionReceipt::dispatchSync($payment->id);

        Mail::assertSent(
            StayExtensionReceipt::class,
            fn (StayExtensionReceipt $m) => $m->hasTo('guest@example.test') && $m->payment->is($payment),
        );
    }

    public function test_it_skips_when_the_guest_has_no_email(): void
    {
        Mail::fake();
        [, $payment] = $this->make(email: null);

        SendStayExtensionReceipt::dispatchSync($payment->id);

        Mail::assertNothingSent();
    }

    public function test_it_skips_a_payment_that_did_not_succeed(): void
    {
        Mail::fake();
        [, $payment] = $this->make(status: StayExtensionPayment::PENDING);

        SendStayExtensionReceipt::dispatchSync($payment->id);

        Mail::assertNothingSent();
    }

    public function test_the_receipt_renders_the_confirmed_payment(): void
    {
        [$booking, $payment] = $this->make();

        $mailable = new StayExtensionReceipt($booking, $payment);

        $mailable->assertSeeInHtml('Payment confirmed');
        $mailable->assertSeeInHtml($payment->txnId());
        $mailable->assertSeeInHtml($booking->bookingCode());
        $mailable->assertSeeInHtml('2 nights');
        $mailable->assertSeeInHtml('VAT (7.5%)');
        $mailable->assertSeeInHtml('₦15,000');  // subtotal (amount − VAT)
        $mailable->assertSeeInHtml('₦1,125');   // VAT
        $mailable->assertSeeInHtml('₦16,125');  // total paid
    }
}
