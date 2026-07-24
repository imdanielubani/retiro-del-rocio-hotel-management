<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\VisitorPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Visitor passes issued from a guest's in-room tablet.
 */
class VisitorPassTest extends TestCase
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
            'slug' => 'alba-suite-vp',
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

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-vp']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-VP-101',
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

    public function test_a_guest_issues_a_visitor_pass(): void
    {
        $data = $this->withToken($this->token)
            ->postJson('/api/v1/visitor-passes', [
                'visitor_name' => 'Michael Brown',
                'visitor_email' => 'm.brown@mail.com',
                'visitor_phone' => '+44 7700 900111',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.visitor_name', 'Michael Brown')
            ->json('data');

        // A live 6-digit entry code is minted server-side.
        $this->assertMatchesRegularExpression('/^\d{6}$/', $data['code']);

        // The host + room are snapshotted onto the pass so it still reads
        // correctly after check-out.
        $this->assertDatabaseHas('visitor_passes', [
            'room_unit_id' => $this->unit->id,
            'visitor_name' => 'Michael Brown',
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'status' => 'pending',
        ]);
    }

    public function test_the_name_is_required(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('visitor_name');
    }

    public function test_the_history_lists_this_rooms_passes_newest_first(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/visitor-passes', ['visitor_name' => 'First Visitor'])->assertCreated();
        $this->withToken($this->token)->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Second Visitor'])->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/v1/visitor-passes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.visitor_name', 'Second Visitor')
            ->assertJsonPath('data.1.visitor_name', 'First Visitor');
    }

    public function test_a_tablet_only_sees_its_own_rooms_passes(): void
    {
        // A pass belonging to another room must never appear on this tablet.
        $otherUnit = RoomUnit::create([
            'room_id' => $this->unit->room_id,
            'number' => '102',
            'status' => 'occupied',
        ]);
        VisitorPass::create([
            'room_unit_id' => $otherUnit->id,
            'visitor_name' => 'Someone Else',
            'code' => '000111',
            'status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/visitor-passes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_next_guest_never_sees_the_previous_guests_visitors(): void
    {
        // Daniel invites someone, then checks out.
        $this->withToken($this->token)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Michael Brown'])
            ->assertCreated();

        $previous = $this->unit->booking;
        $previous->update(['status' => 'checked_out', 'checked_out_at' => now()]);

        // Between stays the tablet has nothing to show, and nothing to issue.
        $this->withToken($this->token)->getJson('/api/v1/visitor-passes')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($this->token)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Nobody'])
            ->assertStatus(409);

        // Reception checks the next guest into the same room.
        $next = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Zara Ahmed',
            'room_id' => $this->unit->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 2, 'guests' => 1, 'amount' => 300000,
            'status' => 'checked_in', 'checked_in_at' => now(),
        ]);
        $this->unit->update(['booking_id' => $next->id]);

        // Zara starts clean — Daniel's visitor is not hers to see.
        $this->withToken($this->token)->getJson('/api/v1/visitor-passes')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->token)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Her Own Guest'])
            ->assertCreated()
            ->assertJsonPath('data.visitor_name', 'Her Own Guest');

        $this->withToken($this->token)->getJson('/api/v1/visitor-passes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.visitor_name', 'Her Own Guest');

        // Daniel's pass is still on file for the register — hidden, not deleted.
        $this->assertDatabaseHas('visitor_passes', [
            'booking_id' => $previous->id,
            'visitor_name' => 'Michael Brown',
        ]);
    }

    public function test_generated_codes_are_unique_among_open_passes(): void
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->withToken($this->token)
                ->postJson('/api/v1/visitor-passes', ['visitor_name' => "Visitor {$i}"])
                ->assertCreated()
                ->json('data.code');
        }

        $this->assertSame(count($codes), count(array_unique($codes)), 'Every live pass should carry a distinct code.');
    }

    public function test_an_unauthenticated_tablet_cannot_issue_a_pass(): void
    {
        $this->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Nope'])
            ->assertUnauthorized();
    }
}
