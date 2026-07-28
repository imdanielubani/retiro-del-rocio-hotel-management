<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Bookings\Index;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Bookings → Cancellations: a card reversal must actually debit the
 * charge back through Paystack, not just flip a flag in the database.
 */
class BookingRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret_key', 'sk_test_refund');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');

        return $user;
    }

    private function paidBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'paystack-'.Str::lower(Str::random(10)),
            'customer_name' => 'Daniel Ubani',
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 50000,
            'status' => 'cancelled',
            'payment_method' => 'card',
            'paid_at' => now(),
        ], $overrides));
    }

    public function test_a_card_reversal_is_debited_through_paystack(): void
    {
        Http::fake(['*/refund' => Http::response(['status' => true, 'data' => ['id' => 99, 'status' => 'pending']], 200)]);
        $booking = $this->paidBooking();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectCancellation', $booking->id)
            ->set('refundMethod', 'card_reversal')
            ->set('refundAmount', 40000)
            ->call('issueRefund', $booking->id);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/refund')
            && $req['transaction'] === $booking->reference
            && $req['amount'] === 4000000); // 40,000 in kobo

        $fresh = $booking->fresh();
        $this->assertSame('completed', $fresh->refund_status);
        $this->assertSame(40000, (int) $fresh->refund_amount);
    }

    public function test_a_declined_paystack_refund_stays_pending(): void
    {
        Http::fake(['*/refund' => Http::response(['status' => false, 'message' => 'Transaction not found'], 400)]);
        $booking = $this->paidBooking();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectCancellation', $booking->id)
            ->set('refundMethod', 'card_reversal')
            ->set('refundAmount', 40000)
            ->call('issueRefund', $booking->id);

        // Not marked done, so the desk can retry — money was not returned.
        $this->assertSame('pending', $booking->fresh()->refund_status);
    }

    public function test_a_bank_transfer_refund_does_not_touch_paystack(): void
    {
        Http::fake();
        $booking = $this->paidBooking();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectCancellation', $booking->id)
            ->set('refundMethod', 'bank_transfer')
            ->set('refundAmount', 40000)
            ->call('issueRefund', $booking->id);

        Http::assertNothingSent();
        $this->assertSame('completed', $booking->fresh()->refund_status);
    }

    public function test_a_manual_front_desk_booking_cannot_be_card_reversed(): void
    {
        Http::fake(['*/refund' => Http::response(['status' => true], 200)]);
        $booking = $this->paidBooking(['reference' => 'ADM-'.Str::upper(Str::random(8)), 'payment_method' => 'manual']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('selectCancellation', $booking->id)
            ->set('refundMethod', 'card_reversal')
            ->set('refundAmount', 40000)
            ->call('issueRefund', $booking->id);

        Http::assertNothingSent(); // the service refuses before any HTTP call
        $this->assertSame('pending', $booking->fresh()->refund_status);
    }
}
