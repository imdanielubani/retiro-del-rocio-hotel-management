<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Room;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception Vehicle Pickup module: listing guest vehicle bookings, the
 * assignable driver roster, and assigning / completing a pickup.
 */
class ReceptionVehiclePickupTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence',
            'type' => 'suite',
            'price' => 150000,
        ]);
    }

    private function token(string $role = 'reception'): string
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function pickupBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Fatima Al-Rashid',
            'customer_phone' => '+2348012345678',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2, 'guests' => 2, 'amount' => 300000,
            'status' => 'paid',
            'pickup_vehicle' => 'Toyota Sienna',
            'pickup_price' => '25000',
            'pickup_passengers' => 2,
            'pickup_flight_number' => 'WB402',
        ], $overrides));
    }

    private function driver(array $overrides = []): Driver
    {
        return Driver::create(array_merge([
            'name' => 'Musa Bello',
            'phone' => '+2348090000000',
            'vehicle_details' => 'Toyota Sienna · ABC-123-XY',
            'status' => Driver::AVAILABLE,
        ], $overrides));
    }

    public function test_pickups_lists_vehicle_bookings_unassigned_first(): void
    {
        $assigned = $this->pickupBooking(['customer_name' => 'Assigned Guest']);
        $assigned->assignPickupDriver($this->driver());
        $this->pickupBooking(['customer_name' => 'Waiting Guest']);
        // A booking with no vehicle pickup must not appear.
        $this->pickupBooking(['customer_name' => 'No Car', 'pickup_vehicle' => null]);

        $data = $this->withToken($this->token())
            ->getJson('/api/v1/reception/pickups')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $data);
        $this->assertSame('Waiting Guest', $data[0]['guest_name']); // unassigned sorts first
        $this->assertSame('unassigned', $data[0]['pickup_status']);
        $this->assertSame('assigned', $data[1]['pickup_status']);
        $this->assertSame('Musa Bello', $data[1]['driver']['name']);
    }

    public function test_drivers_returns_only_available_roster(): void
    {
        $this->driver(['name' => 'Available Ade']);
        $this->driver(['name' => 'Off Duty Obi', 'status' => Driver::OFF_DUTY]);

        $data = $this->withToken($this->token())
            ->getJson('/api/v1/reception/drivers')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Available Ade', $data[0]['name']);
    }

    public function test_assign_driver_records_the_assignment(): void
    {
        $booking = $this->pickupBooking();
        $driver = $this->driver();

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/assign-driver", [
                'driver_id' => $driver->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.pickup_status', 'assigned')
            ->assertJsonPath('data.driver.name', 'Musa Bello');

        $booking->refresh();
        $this->assertSame($driver->id, $booking->pickup_driver_id);
        $this->assertSame('assigned', $booking->pickup_status);
        $this->assertNotNull($booking->pickup_assigned_at);
    }

    public function test_assign_driver_with_null_clears_the_assignment(): void
    {
        $booking = $this->pickupBooking();
        $booking->assignPickupDriver($this->driver());

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/assign-driver", [
                'driver_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.pickup_status', 'unassigned');

        $booking->refresh();
        $this->assertNull($booking->pickup_driver_id);
    }

    public function test_assign_driver_rejects_an_off_duty_driver(): void
    {
        $booking = $this->pickupBooking();
        $offDuty = $this->driver(['status' => Driver::OFF_DUTY]);

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/assign-driver", [
                'driver_id' => $offDuty->id,
            ])
            ->assertStatus(422);
    }

    public function test_completing_a_pickup_requires_an_assigned_driver(): void
    {
        $booking = $this->pickupBooking();

        // Unassigned → cannot complete.
        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/pickup-complete")
            ->assertStatus(409);

        $booking->assignPickupDriver($this->driver());

        $this->withToken($this->token())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/pickup-complete")
            ->assertOk()
            ->assertJsonPath('data.pickup_status', 'completed');
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $booking = $this->pickupBooking();

        $this->withToken($this->token('security'))
            ->getJson('/api/v1/reception/pickups')
            ->assertStatus(403);
    }
}
