<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\LostFoundItem;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The housekeeping tablet's Lost & Found log: items found while turning over
 * a room, from first logged through to being handed back or disposed of.
 */
class HousekeepingLostFoundTest extends TestCase
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
            'slug' => 'alba-suite-lf-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(array_merge([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_an_item_can_be_logged_against_a_room(): void
    {
        $unit = $this->unit(['number' => '204']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Grace Hopper',
            'room_id' => $unit->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $data = $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/lost-found', [
                'room_unit_id' => $unit->id,
                'item_description' => 'Blue phone charger',
                'notes' => 'Found under the bed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.room_number', '204')
            ->assertJsonPath('data.item_description', 'Blue phone charger')
            ->assertJsonPath('data.status', 'unclaimed')
            ->assertJsonPath('data.is_unclaimed', true)
            ->assertJsonPath('data.found_by_name', 'Ada Lovelace')
            ->json('data');

        $this->assertDatabaseHas('lost_found_items', [
            'id' => $data['id'],
            'room_unit_id' => $unit->id,
            'booking_id' => $booking->id,
        ]);
    }

    public function test_an_item_can_be_logged_with_no_room_at_all(): void
    {
        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/lost-found', [
                'item_description' => 'Sunglasses left in the lobby',
            ])
            ->assertCreated()
            ->assertJsonPath('data.room_number', null)
            ->assertJsonPath('data.item_description', 'Sunglasses left in the lobby');
    }

    public function test_the_description_is_required(): void
    {
        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/lost-found', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_description');
    }

    public function test_items_list_unclaimed_first_then_newest(): void
    {
        $unit = $this->unit();
        $older = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Old unclaimed item',
            'found_at' => now()->subDays(3),
        ]);
        $returned = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Already returned item',
            'found_at' => now()->subDay(),
            'status' => LostFoundItem::RETURNED,
            'returned_at' => now(),
        ]);
        $newer = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'New unclaimed item',
            'found_at' => now(),
        ]);

        $data = $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/lost-found')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->json('data');

        $this->assertSame($newer->id, $data[0]['id']);
        $this->assertSame($older->id, $data[1]['id']);
        $this->assertSame($returned->id, $data[2]['id']);
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        $unit = $this->unit();
        LostFoundItem::create(['room_unit_id' => $unit->id, 'item_description' => 'A', 'found_at' => now()]);
        LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'B',
            'found_at' => now(),
            'status' => LostFoundItem::RETURNED,
            'returned_at' => now(),
        ]);

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/lost-found?status=returned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_description', 'B');
    }

    public function test_an_item_can_be_marked_returned_with_the_claimants_details(): void
    {
        $unit = $this->unit();
        $item = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Blue phone charger',
            'found_at' => now(),
        ]);

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/lost-found/{$item->id}/status", [
                'action' => 'returned',
                'claimant_name' => 'Grace Hopper',
                'claimant_contact' => 'grace@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.is_unclaimed', false)
            ->assertJsonPath('data.claimant_name', 'Grace Hopper')
            ->assertJsonPath('data.claimant_contact', 'grace@example.com');

        $this->assertNotNull($item->fresh()->returned_at);
        $this->assertNotNull($item->fresh()->returned_by);
    }

    public function test_an_item_can_be_marked_disposed(): void
    {
        $unit = $this->unit();
        $item = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Odd sock',
            'found_at' => now(),
        ]);

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/lost-found/{$item->id}/status", ['action' => 'disposed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disposed');
    }

    public function test_an_already_returned_item_cannot_be_returned_again(): void
    {
        $unit = $this->unit();
        $item = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Blue phone charger',
            'found_at' => now(),
            'status' => LostFoundItem::RETURNED,
            'claimant_name' => 'Grace Hopper',
            'returned_at' => now()->subHour(),
        ]);
        $firstReturnedAt = $item->returned_at;

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/lost-found/{$item->id}/status", [
                'action' => 'returned',
                'claimant_name' => 'Someone Else',
            ])
            ->assertOk()
            ->assertJsonPath('data.claimant_name', 'Grace Hopper');

        $this->assertTrue($item->fresh()->returned_at->equalTo($firstReturnedAt));
    }

    public function test_an_invalid_action_is_rejected(): void
    {
        $unit = $this->unit();
        $item = LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Blue phone charger',
            'found_at' => now(),
        ]);

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/lost-found/{$item->id}/status", ['action' => 'lost-forever'])
            ->assertStatus(422);
    }

    public function test_a_non_housekeeping_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/housekeeping/lost-found')
            ->assertForbidden();

        $this->withToken($this->otherRoleToken())
            ->postJson('/api/v1/housekeeping/lost-found', ['item_description' => 'Test'])
            ->assertForbidden();
    }
}
