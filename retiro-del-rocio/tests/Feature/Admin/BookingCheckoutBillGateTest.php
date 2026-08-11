<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Bookings\Show;
use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Bookings → Show: checkout must be blocked while the guest has an
 * outstanding room-charge balance — the same gate the reception tablet
 * enforces, so an admin can't bypass it from the dashboard.
 */
class BookingCheckoutBillGateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');

        return $user;
    }

    private function checkedInBooking(array $overrides = []): Booking
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::lower(Str::random(8)),
            'type' => 'suite',
            'price' => 10625,
        ]);
        $unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '301',
            'status' => 'occupied',
        ]);

        $booking = Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_name' => 'Alba Suite',
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'nights' => 2,
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
            'reference' => 'SPA-ADMIN-GATE-'.Str::upper(Str::random(8)),
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);
    }

    public function test_checkout_is_blocked_while_a_room_charge_is_outstanding(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['booking' => $booking])
            ->call('checkOut')
            ->assertDispatched('toast', type: 'error');

        $this->assertSame('checked_in', $booking->fresh()->status);
    }

    public function test_checkout_succeeds_once_settled(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);
        BillPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'BILL-ADMIN-GATE-1',
            'amount' => 15000,
            'vat' => 1125,
            'status' => BillPayment::SUCCESS,
            'paid_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['booking' => $booking])
            ->call('checkOut')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('checked_out', $booking->fresh()->status);
    }

    public function test_checkout_with_no_extra_charges_is_not_blocked(): void
    {
        $booking = $this->checkedInBooking();

        Livewire::actingAs($this->admin())
            ->test(Show::class, ['booking' => $booking])
            ->call('checkOut')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('checked_out', $booking->fresh()->status);
    }
}
