<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payment\Index;
use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\CinemaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Payments: a cinema booking charged straight to the room follows
 * the same rule as a room-charge spa session — it must not inflate the
 * Payments ledger as revenue until the guest's folio is actually settled,
 * and even then it's the settlement, not the booking itself, that counts.
 */
class PaymentCinemaTransactionTest extends TestCase
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

    private function cinemaBooking(int $bookingId, array $overrides = []): CinemaBooking
    {
        return CinemaBooking::create(array_merge([
            'booking_id' => $bookingId,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-'.Str::upper(Str::random(8)),
            'movie_title' => 'Dune',
            'show_date' => now()->toDateString(),
            'show_time' => '7:00 PM',
            'room' => 'Room 1',
            'guests' => 2,
            'snacks' => [],
            'subtotal' => 10000,
            'vat' => 750,
            'amount' => 10000,
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_a_paid_cinema_booking_appears_as_a_dated_transaction(): void
    {
        $booking = $this->booking();

        $this->cinemaBooking($booking->id, [
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Cinema')
            ->assertSee('₦10,750'); // ₦10,000 base + ₦750 VAT
    }

    public function test_a_room_charge_cinema_booking_is_not_shown_as_a_paid_transaction(): void
    {
        $booking = $this->booking();

        $cinemaBooking = $this->cinemaBooking($booking->id, [
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee($cinemaBooking->txnId())
            ->assertDontSee('₦10,750');
    }

    public function test_a_settled_room_charge_cinema_booking_still_does_not_double_count(): void
    {
        $booking = $this->booking();

        $cinemaBooking = $this->cinemaBooking($booking->id, [
            'show_date' => now()->subMonth()->toDateString(),
            // The state a real settlement leaves it in — paid, dated today.
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $settlement = BillPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'BILL-'.$booking->id.'-CINEMA1',
            'amount' => 10000,
            'vat' => 750,
            'status' => BillPayment::SUCCESS,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee($cinemaBooking->txnId())
            ->assertSee($settlement->txnId())
            ->assertSee('₦10,750'); // the settlement's total — the only place this money appears
    }
}
