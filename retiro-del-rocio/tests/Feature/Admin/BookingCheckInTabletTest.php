<?php

namespace Tests\Feature\Admin;

use App\Events\RoomStatusChanged;
use App\Livewire\Admin\Bookings\Show;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Support\HotelSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The reception loop: an admin checks a booking in and allocates a room number,
 * and the tablet bound to that room immediately reports the guest. Checking the
 * booking out frees the room and the tablet reports it as available again.
 */
class BookingCheckInTabletTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    private Device $device;

    private string $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-test',
            'type' => 'suite',
            'price' => 150000,
            'featured_image' => 'images/alba-suite.jpg',
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '101',
            'status' => 'available',
        ]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-test']);

        $this->device = Device::create([
            'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'device_code' => 'TAB-TEST-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->deviceToken = $this->device->createToken('tablet')->plainTextToken;
    }

    private function roomStatus(): array
    {
        return $this->withToken($this->deviceToken)
            ->getJson('/api/v1/tablets/room-status')
            ->assertOk()
            ->json('data');
    }

    private function booking(string $status = 'paid'): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'guest@example.test',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 750000,
            'status' => $status,
        ]);
    }

    public function test_tablet_reports_available_before_any_check_in(): void
    {
        $status = $this->roomStatus();

        $this->assertSame('available', $status['occupancy']);
        $this->assertSame('Alba Suite', $status['suite_name']);
        $this->assertSame('101', $status['room_number']);
        $this->assertNull($status['guest']);
    }

    public function test_check_in_allocates_the_room_and_the_tablet_shows_the_guest(): void
    {
        $booking = $this->booking();

        Livewire::test(Show::class, ['booking' => $booking])
            ->call('startCheckIn')
            ->set('assignUnitId', $this->unit->id)
            ->call('confirmCheckIn')
            ->assertHasNoErrors();

        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertSame($this->unit->id, $booking->fresh()->room_unit_id);
        $this->assertSame('occupied', $this->unit->fresh()->status);
        $this->assertSame($booking->id, $this->unit->fresh()->booking_id);

        $status = $this->roomStatus();

        $this->assertSame('occupied', $status['occupancy']);
        $this->assertSame('Daniel Ubani', $status['guest']['name']);
        $this->assertSame(5, $status['guest']['nights']);

        // Arrival is the moment reception actually checked them in; departure is
        // still the expected time (the hotel's 11:00 AM policy).
        $this->assertTrue(
            Carbon::parse($status['guest']['check_in'])->diffInMinutes(now()) < 2
        );
        $this->assertSame('11:00', Carbon::parse($status['guest']['check_out'])->format('H:i'));
    }

    public function test_the_tablet_gets_the_rooms_own_photo_at_launch(): void
    {
        // The photo belongs to the device's room, not to a booking, so it rides
        // on the device record the tablet fetches once — not on the 20s poll.
        $device = $this->withToken($this->deviceToken)
            ->getJson('/api/v1/tablets/me')
            ->assertOk()
            ->json('device');

        $this->assertStringContainsString('alba-suite', (string) $device['room_image']);
        $this->assertArrayNotHasKey('room_image', $this->roomStatus());
    }

    public function test_a_check_in_and_check_out_are_broadcast_to_the_room(): void
    {
        Event::fake([RoomStatusChanged::class]);

        $booking = $this->booking();

        $component = Livewire::test(Show::class, ['booking' => $booking])
            ->call('startCheckIn')
            ->set('assignUnitId', $this->unit->id)
            ->call('confirmCheckIn');

        // The tablet is told the instant reception acts — no 20-second wait.
        Event::assertDispatched(
            RoomStatusChanged::class,
            fn (RoomStatusChanged $e) => $e->roomUnitId === $this->unit->id
                && $e->occupancy === 'occupied'
        );

        $component->call('checkOut');

        Event::assertDispatched(
            RoomStatusChanged::class,
            fn (RoomStatusChanged $e) => $e->roomUnitId === $this->unit->id
                && $e->occupancy === 'available'
        );
    }

    public function test_the_recorded_arrival_survives_a_policy_change(): void
    {
        $booking = $this->booking();

        $this->travelTo(now()->setTime(16, 42));

        Livewire::test(Show::class, ['booking' => $booking])
            ->call('startCheckIn')
            ->set('assignUnitId', $this->unit->id)
            ->call('confirmCheckIn');

        $this->travelBack();

        // Reception checked them in at 4:42 PM — not the 3:00 PM policy time.
        $this->assertSame('16:42', $booking->fresh()->checked_in_at->format('H:i'));
        $this->assertSame('16:42', Carbon::parse($this->roomStatus()['guest']['check_in'])->format('H:i'));

        // Changing the policy must not rewrite what already happened.
        HotelSettings::setCheckInTime('09:00');

        $this->assertSame('16:42', Carbon::parse($this->roomStatus()['guest']['check_in'])->format('H:i'));
    }

    public function test_the_expected_departure_follows_the_hotel_setting(): void
    {
        $booking = $this->booking();

        Livewire::test(Show::class, ['booking' => $booking])
            ->call('startCheckIn')
            ->set('assignUnitId', $this->unit->id)
            ->call('confirmCheckIn');

        // The guest has not left yet, so departure is the policy time.
        $this->assertSame(
            '11:00',
            Carbon::parse($this->roomStatus()['guest']['check_out'])->format('H:i')
        );

        // An admin changes the policy in Settings → Hotel Info; the tablet picks
        // it up on its next poll, with no redeploy.
        HotelSettings::setCheckOutTime('10:00');

        $this->assertSame(
            '10:00',
            Carbon::parse($this->roomStatus()['guest']['check_out'])->format('H:i')
        );
    }

    public function test_a_paid_booking_holding_the_room_is_not_shown_as_a_guest(): void
    {
        // Reception has not checked this booking in yet — the tablet must not
        // reveal the guest, even though the booking already holds the room.
        $booking = $this->booking('paid');
        $booking->update(['room_unit_id' => $this->unit->id]);
        $this->unit->update(['status' => 'occupied', 'booking_id' => $booking->id]);

        $status = $this->roomStatus();

        $this->assertSame('occupied', $status['occupancy']);
        $this->assertNull($status['guest']);
    }

    public function test_check_out_frees_the_room_and_the_tablet_shows_available(): void
    {
        $booking = $this->booking();

        $component = Livewire::test(Show::class, ['booking' => $booking])
            ->call('startCheckIn')
            ->set('assignUnitId', $this->unit->id)
            ->call('confirmCheckIn');

        $this->assertSame('occupied', $this->roomStatus()['occupancy']);

        $component->call('checkOut')->assertHasNoErrors();

        $this->assertSame('checked_out', $booking->fresh()->status);
        $this->assertSame('available', $this->unit->fresh()->status);
        $this->assertNull($this->unit->fresh()->booking_id);

        $status = $this->roomStatus();

        $this->assertSame('available', $status['occupancy']);
        $this->assertNull($status['guest']);
    }
}
