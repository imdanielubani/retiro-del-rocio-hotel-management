<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\HousekeepingRequest;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The housekeeping tablet: the dashboard, the room-status board, and the
 * guest-request queue.
 */
class HousekeepingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function housekeeperToken(): string
    {
        Role::findOrCreate('housekeeping', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Ada Lovelace']);
        $user->assignRole('housekeeping');

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
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-hk-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(array_merge([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_overview_lists_rooms_needing_attention_and_pending_requests(): void
    {
        $dirty = $this->unit(['housekeeping_status' => 'dirty']);
        $this->unit(['housekeeping_status' => 'clean']);
        HousekeepingRequest::create([
            'room_unit_id' => $dirty->id,
            'type' => 'towels',
        ]);

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.needs_attention', 1)
            ->assertJsonPath('data.stats.dirty', 1)
            ->assertJsonPath('data.stats.pending_requests', 1)
            ->assertJsonCount(1, 'data.rooms')
            ->assertJsonPath('data.rooms.0.housekeeping_status', 'dirty')
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.type_label', 'Towels');
    }

    public function test_a_checkout_today_room_sorts_before_a_merely_dirty_one(): void
    {
        $stayover = $this->unit(['housekeeping_status' => 'dirty', 'status' => 'occupied']);
        $checkoutRoom = $this->unit(['housekeeping_status' => 'dirty', 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Grace Hopper',
            'room_id' => $checkoutRoom->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $checkoutRoom->id,
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(2),
        ]);
        $checkoutRoom->update(['booking_id' => $booking->id]);

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/overview')
            ->assertOk()
            ->assertJsonPath('data.rooms.0.id', $checkoutRoom->id)
            ->assertJsonPath('data.rooms.0.checkout_today', true)
            ->assertJsonPath('data.rooms.1.id', $stayover->id);
    }

    public function test_rooms_lists_every_room_and_filters_by_status(): void
    {
        $this->unit(['housekeeping_status' => 'dirty']);
        $this->unit(['housekeeping_status' => 'clean']);

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/rooms')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/rooms?status=dirty')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.housekeeping_status', 'dirty');
    }

    public function test_rooms_search_narrows_by_room_number(): void
    {
        $this->unit(['number' => '204', 'housekeeping_status' => 'dirty']);
        $this->unit(['number' => '305', 'housekeeping_status' => 'dirty']);

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/rooms?search=204')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', '204');
    }

    public function test_marking_a_room_clean_updates_its_status(): void
    {
        $unit = $this->unit(['housekeeping_status' => 'dirty']);

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/rooms/{$unit->id}/status", ['status' => 'inspected'])
            ->assertOk()
            ->assertJsonPath('data.housekeeping_status', 'inspected')
            ->assertJsonPath('data.housekeeping_status_label', 'Inspected');

        $this->assertSame('inspected', $unit->fresh()->housekeeping_status);
        $this->assertNotNull($unit->fresh()->housekeeping_status_at);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $unit = $this->unit();

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/rooms/{$unit->id}/status", ['status' => 'sparkling'])
            ->assertStatus(422);
    }

    public function test_requests_lists_pending_before_completed(): void
    {
        $unit = $this->unit();
        $completed = HousekeepingRequest::create(['room_unit_id' => $unit->id, 'type' => 'towels']);
        $completed->complete();
        $pending = HousekeepingRequest::create(['room_unit_id' => $unit->id, 'type' => 'dnd']);

        $data = $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertSame($pending->id, $data[0]['id']);
        $this->assertTrue($data[0]['is_pending']);
        $this->assertFalse($data[1]['is_pending']);
    }

    public function test_a_request_can_be_created_and_then_completed(): void
    {
        $unit = $this->unit();

        $created = $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/requests', [
                'room_unit_id' => $unit->id,
                'type' => 'make_up_room',
                'notes' => 'After 2pm please',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type_label', 'Make Up Room')
            ->assertJsonPath('data.is_pending', true)
            ->json('data');

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/requests/{$created['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_pending', false);

        $this->assertNotNull(HousekeepingRequest::find($created['id'])->completed_at);
    }

    public function test_completing_an_already_completed_request_is_a_no_op(): void
    {
        $unit = $this->unit();
        $entry = HousekeepingRequest::create(['room_unit_id' => $unit->id, 'type' => 'amenities']);
        $entry->complete();
        $firstCompletedAt = $entry->fresh()->completed_at;

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/requests/{$entry->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.is_pending', false);

        $this->assertTrue($entry->fresh()->completed_at->equalTo($firstCompletedAt));
    }

    public function test_a_non_housekeeping_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/housekeeping/overview')
            ->assertForbidden();
    }
}
