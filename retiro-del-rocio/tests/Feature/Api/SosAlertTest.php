<?php

namespace Tests\Feature\Api;

use App\Events\SosAlertChanged;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SosAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Emergency SOS raised from a guest's in-room tablet.
 */
class SosAlertTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private Device $device;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-sos',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 450000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-sos']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-SOS-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $this->device->createToken('tablet')->plainTextToken;
    }

    public function test_a_guest_raises_an_emergency(): void
    {
        Event::fake([SosAlertChanged::class]);

        $this->withToken($this->token)
            ->postJson('/api/v1/sos')
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.room_number', '101')
            // Snapshotted, so the incident record still reads correctly long
            // after the guest has checked out.
            ->assertJsonPath('data.guest_name', 'Daniel Ubani');

        $this->assertDatabaseHas('sos_alerts', [
            'room_unit_id' => $this->unit->id,
            'status' => 'active',
        ]);

        // Security is told immediately — nobody waits on a poll in an emergency.
        Event::assertDispatched(SosAlertChanged::class);
    }

    public function test_hammering_the_button_raises_exactly_one_alert(): void
    {
        // A frightened guest presses repeatedly. A dozen duplicates would bury
        // the one alert that matters.
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->token)->postJson('/api/v1/sos')->assertSuccessful();
        }

        $this->assertSame(1, SosAlert::count());
    }

    public function test_the_tablet_recovers_an_open_alert_after_a_restart(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/sos')->assertCreated();

        // The tablet reboots mid-emergency: it must come back showing "help is on
        // the way", not a fresh SOS button.
        $this->withToken($this->token)
            ->getJson('/api/v1/sos/active')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_the_guest_can_stand_the_alert_down(): void
    {
        $alert = $this->withToken($this->token)
            ->postJson('/api/v1/sos')->json('data');

        $this->withToken($this->token)
            ->postJson("/api/v1/sos/{$alert['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->withToken($this->token)
            ->getJson('/api/v1/sos/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_resolved_alert_cannot_be_rewritten_by_the_guest(): void
    {
        $alert = SosAlert::create([
            'room_unit_id' => $this->unit->id,
            'room_number' => '101',
            'status' => SosAlert::RESOLVED,
            'raised_at' => now()->subMinutes(10),
            'resolved_at' => now(),
        ]);

        // Security dealt with it. The guest cancelling afterwards must not
        // overwrite the incident record.
        $this->withToken($this->token)
            ->postJson("/api/v1/sos/{$alert->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_a_tablet_cannot_cancel_another_rooms_emergency(): void
    {
        $otherUnit = RoomUnit::create([
            'room_id' => $this->unit->room_id,
            'number' => '102',
            'status' => 'occupied',
        ]);

        $alert = SosAlert::create([
            'room_unit_id' => $otherUnit->id,
            'room_number' => '102',
            'status' => SosAlert::ACTIVE,
            'raised_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/sos/{$alert->id}/cancel")
            ->assertForbidden();

        $this->assertSame('active', $alert->fresh()->status);
    }

    public function test_an_unauthenticated_tablet_cannot_raise_an_alert(): void
    {
        $this->postJson('/api/v1/sos')->assertUnauthorized();
    }
}
