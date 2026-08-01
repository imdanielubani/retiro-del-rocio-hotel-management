<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payment\Index;
use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Payments: a spa session charged straight to the room is confirmed
 * immediately but not actually paid for until the guest settles their folio —
 * it must not inflate the Payments ledger as revenue in the meantime.
 */
class PaymentSpaTransactionTest extends TestCase
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

    public function test_a_paid_spa_session_appears_as_a_dated_transaction(): void
    {
        $booking = $this->booking();

        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-'.$booking->id.'-PAIDONLINE',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '2:00 PM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Spa')
            ->assertSee('₦16,125'); // total paid: ₦15,000 base + ₦1,125 VAT
    }

    public function test_a_room_charge_spa_session_is_not_shown_as_a_paid_transaction(): void
    {
        $booking = $this->booking();

        $spaBooking = SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-'.$booking->id.'-ROOMCHARGE1',
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee($spaBooking->txnId())
            ->assertDontSee('₦37,625');
    }

    /**
     * Once the guest actually clears their room-charge balance from the
     * Billing module (a settled BillPayment), that settlement — not the
     * original charge-to-room booking — is what shows up as real revenue in
     * the Payments module.
     */
    public function test_settling_the_room_charge_balance_shows_up_as_a_paid_transaction(): void
    {
        $booking = $this->booking();

        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-'.$booking->id.'-ROOMCHARGE2',
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        $settlement = BillPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'BILL-'.$booking->id.'-SETTLED1',
            'amount' => 35000,
            'vat' => 2625,
            'status' => BillPayment::SUCCESS,
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Bill Settlement')
            ->assertSee($settlement->txnId())
            ->assertSee('₦37,625'); // ₦35,000 base + ₦2,625 VAT actually collected
    }
}
