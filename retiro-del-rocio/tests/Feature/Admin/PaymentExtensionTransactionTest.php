<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payment\Index;
use App\Models\Booking;
use App\Models\StayExtensionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Payments: a guest-paid stay extension shows as its own dated
 * transaction, distinct from the booking's original payment.
 */
class PaymentExtensionTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 52500,
            'status' => 'checked_in',
        ]);
    }

    public function test_a_paid_extension_appears_as_a_dated_transaction(): void
    {
        $booking = $this->booking();

        StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-ABCDEFGHIJ',
            'nights' => 2,
            'new_check_out' => now()->addDays(5)->toDateString(),
            'amount' => 16125,
            'vat' => 1125,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'card',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Extension')          // the source pill
            ->assertSee('₦17,250')            // total paid: ₦16,125 base + ₦1,125 VAT
            ->assertSee('Daniel Ubani')       // host guest, read via the booking
            ->assertSee(now()->format('M j, Y')); // the payment date
    }

    public function test_a_pending_extension_is_not_shown(): void
    {
        $booking = $this->booking();

        StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-PENDINGXYZ',
            'nights' => 1,
            'new_check_out' => now()->addDays(4)->toDateString(),
            'amount' => 8063,
            'vat' => 563,
            'status' => StayExtensionPayment::PENDING,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee('TXN-EXT-'.str_pad((string) StayExtensionPayment::first()->id, 4, '0', STR_PAD_LEFT));
    }

    /**
     * A charge-to-room extension is applied to the stay immediately, but no
     * money has actually landed — it's only settled later via My Bills. It
     * must not appear as a paid transaction in the Payments ledger.
     */
    public function test_a_room_charge_extension_is_not_shown_as_a_paid_transaction(): void
    {
        $booking = $this->booking();

        $payment = StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-ROOMCHARGE1',
            'nights' => 2,
            'new_check_out' => now()->addDays(5)->toDateString(),
            'amount' => 16125,
            'vat' => 1125,
            'status' => StayExtensionPayment::SUCCESS,
            'payment_method' => 'room_charge',
        ]);

        $this->assertFalse($payment->isPaid());

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee('TXN-EXT-'.str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT))
            ->assertDontSee('₦17,250');
    }
}
