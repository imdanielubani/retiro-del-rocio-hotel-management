<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Billing\Index;
use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Billing: every checked-in guest's outstanding room-charge balance,
 * and a single guest's itemised bill in the detail modal.
 *
 * The room rate is always settled at booking time — it's shown on the bill
 * for reference but never counts toward what's still due.
 */
class BillingIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function checkedInBooking(array $overrides = []): Booking
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::lower(Str::random(8)),
            'type' => 'suite',
            'price' => 4250,
        ]);
        $unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 21250,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ], $overrides));

        $unit->update(['booking_id' => $booking->id]);

        return $booking;
    }

    private function roomChargeSpa(Booking $booking): void
    {
        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-BILLING-'.Str::upper(Str::random(8)),
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);
    }

    public function test_a_room_only_stay_shows_settled(): void
    {
        $booking = $this->checkedInBooking();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Daniel Ubani')
            ->assertSee('Settled')
            ->assertSee('NGN 0');
    }

    public function test_a_room_charge_spa_booking_shows_as_outstanding(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Outstanding')
            ->assertSee('NGN 37,625'); // spa subtotal (35,000) + its VAT (2,625)
    }

    public function test_a_checked_out_booking_does_not_appear(): void
    {
        $this->checkedInBooking(['status' => 'checked_out', 'customer_name' => 'Ghost Guest']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee('Ghost Guest');
    }

    public function test_search_narrows_by_guest_name(): void
    {
        $this->checkedInBooking(['customer_name' => 'Ada Lovelace']);
        $this->checkedInBooking(['customer_name' => 'Grace Hopper']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('search', 'Ada')
            ->assertSee('Ada Lovelace')
            ->assertDontSee('Grace Hopper');
    }

    public function test_view_bill_opens_the_itemised_detail_modal(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('viewBill', $booking->id)
            ->assertSet('showDetail', true)
            ->assertSee('Room Charges')
            ->assertSee('Spa & Wellness')
            ->assertSee('Deep Tissue Massage')
            ->assertSee('Total Due')
            ->assertSee('NGN 37,625');
    }

    public function test_a_settled_balance_is_reflected_after_a_bill_payment(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);
        BillPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'BILL-ADMIN-1',
            'amount' => 35000,
            'vat' => 2625,
            'status' => BillPayment::SUCCESS,
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Settled')
            ->assertDontSee('inline-flex items-center rounded-full bg-[#fef3c7]', false);
    }

    public function test_close_detail_clears_the_selection(): void
    {
        $booking = $this->checkedInBooking();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('viewBill', $booking->id)
            ->assertSet('showDetail', true)
            ->call('closeDetail')
            ->assertSet('showDetail', false)
            ->assertSet('selectedBookingId', null);
    }
}
