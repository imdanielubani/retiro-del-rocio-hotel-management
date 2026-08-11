<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's read-only Room Status board — every room's
 * occupancy (from its booking), housekeeping's cleanliness flag, and whether
 * maintenance has an open fault against it.
 */
class ReceptionRoomStatusTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-room-status',
            'type' => 'suite',
            'price' => 150000,
        ]);
    }

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function unit(array $overrides = []): RoomUnit
    {
        return RoomUnit::create(array_merge([
            'room_id' => $this->room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertForbidden();
    }

    public function test_an_occupied_room_shows_the_guest_and_board_status(): void
    {
        $unit = $this->unit(['status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Ada Lovelace',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $unit->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'occupied')
            ->assertJsonPath('data.rooms.0.board_status_label', 'Occupied')
            ->assertJsonPath('data.rooms.0.guest_name', 'Ada Lovelace')
            ->assertJsonPath('data.stats.occupied', 1);
    }

    public function test_a_room_marked_out_of_order_by_housekeeping_shows_as_maintenance(): void
    {
        $this->unit(['housekeeping_status' => 'out_of_order']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'maintenance')
            ->assertJsonPath('data.stats.maintenance', 1);
    }

    public function test_a_room_with_an_open_work_order_shows_as_maintenance(): void
    {
        $unit = $this->unit();
        WorkOrder::create([
            'room_unit_id' => $unit->id,
            'title' => 'AC not cooling',
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'maintenance')
            ->assertJsonPath('data.rooms.0.has_open_work_order', true)
            ->assertJsonPath('data.stats.maintenance', 1);
    }

    public function test_a_room_with_only_a_completed_work_order_is_not_maintenance(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create([
            'room_unit_id' => $unit->id,
            'title' => 'Leaky tap',
        ]);
        $order->complete();

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'ready')
            ->assertJsonPath('data.rooms.0.has_open_work_order', false);
    }

    public function test_a_dirty_vacant_room_shows_as_dirty(): void
    {
        $this->unit(['housekeeping_status' => 'dirty']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'dirty')
            ->assertJsonPath('data.stats.dirty', 1);
    }

    public function test_a_room_housekeeping_is_actively_preparing_shows_as_preparing(): void
    {
        $this->unit(['housekeeping_status' => 'preparing']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'preparing')
            ->assertJsonPath('data.stats.preparing', 1);
    }

    public function test_a_clean_vacant_room_is_ready(): void
    {
        $this->unit(['housekeeping_status' => 'clean']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/room-status')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.board_status', 'ready');
    }
}
