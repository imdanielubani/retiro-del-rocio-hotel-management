<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Vehicles\Bookings;
use App\Livewire\Admin\Vehicles\Drivers;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Vehicle Pickups → Drivers roster, and assigning a driver from the
 * Vehicle Pickup bookings detail modal.
 */
class DriverRosterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function pickupBooking(): Booking
    {
        $room = Room::create(['name' => 'Brisa', 'slug' => 'brisa', 'type' => 'suite', 'price' => 150000]);

        return Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Fatima Al-Rashid',
            'room_id' => $room->id, 'room_name' => $room->name,
            'check_in' => today()->toDateString(), 'check_out' => today()->addDay()->toDateString(),
            'nights' => 1, 'guests' => 2, 'amount' => 175000,
            'status' => 'paid',
            'pickup_vehicle' => 'Toyota Sienna', 'pickup_price' => '25000',
        ]);
    }

    public function test_admin_can_add_a_driver_to_the_roster(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->call('openCreate')
            ->set('fName', 'Musa Bello')
            ->set('fPhone', '+2348090000000')
            ->set('fVehicle', 'Toyota Sienna · ABC-123-XY')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('drivers', ['name' => 'Musa Bello', 'status' => 'available']);
    }

    public function test_toggling_status_takes_a_driver_off_duty(): void
    {
        $driver = Driver::create(['name' => 'Ade', 'status' => Driver::AVAILABLE]);

        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->call('toggleStatus', $driver->id);

        $this->assertSame(Driver::OFF_DUTY, $driver->fresh()->status);
    }

    public function test_search_and_status_filters_narrow_the_roster(): void
    {
        Driver::create(['name' => 'Musa Bello', 'phone' => '0801', 'status' => Driver::AVAILABLE]);
        Driver::create(['name' => 'Ada Available', 'status' => Driver::AVAILABLE]);
        Driver::create(['name' => 'Obi Off', 'status' => Driver::OFF_DUTY]);

        // Search matches by name.
        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->set('search', 'Musa')
            ->assertViewHas('filteredCount', 1)
            ->assertSee('Musa Bello')
            ->assertDontSee('Ada Available');

        // Status pill keeps only available drivers.
        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->set('statusFilter', 'off_duty')
            ->assertViewHas('filteredCount', 1)
            ->assertSee('Obi Off')
            ->assertDontSee('Musa Bello');
    }

    public function test_on_trip_filter_shows_only_assigned_drivers(): void
    {
        $onTrip = Driver::create(['name' => 'Busy Ben', 'status' => Driver::AVAILABLE]);
        Driver::create(['name' => 'Free Fred', 'status' => Driver::AVAILABLE]);
        $this->pickupBooking()->assignPickupDriver($onTrip);

        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->set('statusFilter', 'on_trip')
            ->assertViewHas('filteredCount', 1)
            ->assertSee('Busy Ben')
            ->assertDontSee('Free Fred');
    }

    public function test_clear_all_resets_the_filters(): void
    {
        Driver::create(['name' => 'Solo Driver', 'status' => Driver::AVAILABLE]);

        Livewire::actingAs($this->admin())
            ->test(Drivers::class)
            ->set('search', 'nobody')
            ->set('statusFilter', 'off_duty')
            ->set('sort', 'recent')
            ->assertViewHas('filteredCount', 0)
            ->call('clearAll')
            ->assertViewHas('filteredCount', 1)
            ->assertSet('search', '')
            ->assertSet('statusFilter', '')
            ->assertSet('sort', 'name');
    }

    public function test_admin_assigns_a_driver_from_the_booking_modal(): void
    {
        $booking = $this->pickupBooking();
        $driver = Driver::create(['name' => 'Musa Bello', 'status' => Driver::AVAILABLE]);

        Livewire::actingAs($this->admin())
            ->test(Bookings::class)
            ->call('view', $booking->id)
            ->set('assignDriverId', $driver->id)
            ->call('saveDriver');

        $booking->refresh();
        $this->assertSame($driver->id, $booking->pickup_driver_id);
        $this->assertSame('assigned', $booking->pickup_status);
    }

    public function test_admin_marks_a_pickup_as_collected(): void
    {
        $booking = $this->pickupBooking();
        $booking->assignPickupDriver(Driver::create(['name' => 'Musa', 'status' => Driver::AVAILABLE]));

        Livewire::actingAs($this->admin())
            ->test(Bookings::class)
            ->call('markPickedUp', $booking->id);

        $this->assertSame('completed', $booking->fresh()->pickup_status);
    }
}
