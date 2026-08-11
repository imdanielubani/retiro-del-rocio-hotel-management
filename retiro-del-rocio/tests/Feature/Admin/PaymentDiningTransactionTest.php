<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Payment\Index;
use App\Models\BarTab;
use App\Models\Booking;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Payments: Kitchen (food) and Bar & Lounge (drinks) dining orders —
 * previously entirely absent from the ledger — now appear as their own
 * sources, following the same room-charge-deferral rule as Spa/Cinema, with
 * a carve-out for Bar Tablet POS tabs (no booking to defer to).
 */
class PaymentDiningTransactionTest extends TestCase
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

    public function test_a_paid_kitchen_order_appears_as_a_dated_transaction(): void
    {
        $booking = $this->booking();

        DiningOrder::create([
            'booking_id' => $booking->id,
            'reference' => 'DN-'.$booking->id.'-PAIDONLINE',
            'items' => [['name' => 'Salmon', 'price' => 9000, 'qty' => 1]],
            'has_food' => true,
            'has_drinks' => false,
            'item_count' => 1,
            'subtotal' => 9000,
            'vat' => 675,
            'service_fee' => 1000,
            'total' => 10675,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Kitchen')
            ->assertSee('₦10,675');
    }

    public function test_a_paid_drinks_only_order_appears_as_bar_and_lounge(): void
    {
        $booking = $this->booking();

        DiningOrder::create([
            'booking_id' => $booking->id,
            'reference' => 'DN-'.$booking->id.'-DRINK',
            'items' => [['name' => 'Fresh Chapman', 'price' => 3500, 'qty' => 1]],
            'has_food' => false,
            'has_drinks' => true,
            'item_count' => 1,
            'subtotal' => 3500,
            'vat' => 263,
            'service_fee' => 1000,
            'total' => 4763,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Bar & Lounge')
            ->assertSee('₦4,763');
    }

    /** A mixed order (food + drinks) counts once, under Kitchen, never both. */
    public function test_a_mixed_order_is_not_double_counted(): void
    {
        $booking = $this->booking();

        $order = DiningOrder::create([
            'booking_id' => $booking->id,
            'reference' => 'DN-'.$booking->id.'-MIXED',
            'items' => [
                ['name' => 'Salmon', 'price' => 9000, 'qty' => 1],
                ['name' => 'Fresh Chapman', 'price' => 3500, 'qty' => 1],
            ],
            'has_food' => true,
            'has_drinks' => true,
            'item_count' => 2,
            'subtotal' => 12500,
            'vat' => 938,
            'service_fee' => 1000,
            'total' => 14438,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->assertSame('Kitchen', $order->sourceLabel());

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('₦14,438');

        // Not duplicated in the underlying department totals either.
        $this->assertSame(0, DiningOrder::forBarLounge()->where('has_food', false)->count());
    }

    /** A guest-tablet room-charge order is deferred to the room folio — not shown as paid yet. */
    public function test_a_room_charge_guest_order_is_not_shown_as_a_paid_transaction(): void
    {
        $booking = $this->booking();

        $order = DiningOrder::create([
            'booking_id' => $booking->id,
            'reference' => 'DN-'.$booking->id.'-ROOMCHARGE',
            'items' => [['name' => 'Wagyu', 'price' => 15000, 'qty' => 1]],
            'has_food' => true,
            'has_drinks' => false,
            'item_count' => 1,
            'subtotal' => 15000,
            'vat' => 1125,
            'service_fee' => 1000,
            'total' => 17125,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee($order->txnId())
            ->assertDontSee('₦17,125');
    }

    /**
     * A Bar Tablet POS tab charged to a room has no booking to defer to (a
     * walk-in has no folio) — it's already fully paid at settle time and
     * must show up as real revenue, unlike a guest-tablet room-charge order.
     */
    public function test_a_pos_tab_settled_as_room_charge_still_counts_as_paid(): void
    {
        $tab = BarTab::create([
            'code' => BarTab::makeCode(),
            'table_label' => 'Table 4',
            'status' => 'open',
        ]);

        $order = DiningOrder::create([
            'bar_tab_id' => $tab->id,
            'reference' => 'BAR-'.$tab->id.'-TEST',
            'items' => [['name' => 'House Red Wine', 'price' => 6000, 'qty' => 1]],
            'has_food' => false,
            'has_drinks' => true,
            'item_count' => 1,
            'subtotal' => 6000,
            'vat' => 450,
            'service_fee' => 1000,
            'total' => 7450,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee($order->txnId())
            ->assertSee('₦7,450');
    }
}
