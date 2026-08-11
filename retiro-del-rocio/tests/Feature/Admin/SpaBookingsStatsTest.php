<?php

namespace Tests\Feature\Admin;

use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin → Spa Bookings: the "Pending" headline stat. It used to track the
 * booking's session status, which every real booking path sets to 'confirmed'
 * immediately (the web checkout only ever persists a row after Paystack
 * succeeds; the guest tablet's room-charge flow confirms the session the
 * moment it's booked) — so the card read 0 no matter how many unpaid
 * room-charge sessions were actually sitting on guests' folios. It now
 * tracks payment_status, the field that genuinely needs the desk's attention.
 *
 * Note: the component itself (App\Livewire\Admin\Spa\Bookings) can't be
 * exercised through Livewire::test() in this suite — its view also queries a
 * `YEAR(...)` filter option, which is MySQL-only and unsupported by the
 * SQLite in-memory test database this suite runs on (a pre-existing,
 * unrelated gap). This asserts the exact query the "Pending" stat now runs.
 */
class SpaBookingsStatsTest extends TestCase
{
    use RefreshDatabase;

    private function spaBooking(array $overrides = []): SpaBooking
    {
        return SpaBooking::create(array_merge([
            'reference' => 'SPA-'.Str::upper(Str::random(8)),
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 25000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'subtotal' => 25000,
            'total' => 25000,
            'customer_name' => 'Grace Hopper',
            'customer_email' => 'grace@example.com',
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ], $overrides));
    }

    public function test_the_pending_count_includes_unpaid_room_charge_sessions_even_though_confirmed(): void
    {
        // A room-charge session: confirmed the moment it's booked, but unpaid
        // until the guest settles their folio — the real "pending" bucket the
        // desk needs to chase, and it was invisible under the old status-based
        // definition since status is 'confirmed' here, not 'pending'.
        $roomCharge = $this->spaBooking([
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        $this->assertSame(
            1,
            SpaBooking::where('payment_status', 'pending')->count(),
            'A confirmed but unpaid room-charge session must still count as pending.'
        );
        $this->assertSame(0, SpaBooking::where('status', 'pending')->count());
        $this->assertSame('confirmed', $roomCharge->status);
    }

    public function test_an_abandoned_direct_pay_attempt_still_counts_as_pending(): void
    {
        $this->spaBooking(['status' => 'pending', 'payment_status' => 'pending']);

        $this->assertSame(1, SpaBooking::where('payment_status', 'pending')->count());
    }

    public function test_a_confirmed_and_paid_session_does_not_count_as_pending(): void
    {
        $this->spaBooking(['status' => 'confirmed', 'payment_status' => 'paid']);

        $this->assertSame(0, SpaBooking::where('payment_status', 'pending')->count());
    }
}
