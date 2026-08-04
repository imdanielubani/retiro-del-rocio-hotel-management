<?php

namespace Tests\Feature\Api;

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
 * A housekeeper reporting a fault noticed while turning over a room — routed
 * straight to maintenance's own Work Orders board, since marking a room
 * out_of_order only blocks it without telling maintenance what's wrong.
 */
class HousekeepingReportFaultTest extends TestCase
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
            'slug' => 'alba-suite-rf-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(array_merge([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_a_housekeeper_can_report_a_fault_against_a_room(): void
    {
        $unit = $this->unit(['number' => '204']);

        $data = $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/work-orders', [
                'room_unit_id' => $unit->id,
                'title' => 'AC not cooling',
                'description' => 'Noticed while doing the turnover clean',
                'priority' => 'high',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'AC not cooling')
            ->assertJsonPath('data.room_number', '204')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.reported_by', 'Ada Lovelace')
            ->json('data');

        $this->assertDatabaseHas('work_orders', [
            'id' => $data['id'],
            'room_unit_id' => $unit->id,
            'title' => 'AC not cooling',
            'reported_by' => 'Ada Lovelace',
        ]);
    }

    public function test_the_fault_lands_on_maintenances_own_board(): void
    {
        $unit = $this->unit();

        $created = $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/work-orders', [
                'room_unit_id' => $unit->id,
                'title' => 'Leaky faucet',
            ])
            ->assertCreated()
            ->json('data');

        Role::findOrCreate('maintenance', 'web');
        $technician = User::factory()->create(['status' => 'active']);
        $technician->assignRole('maintenance');
        $maintenanceToken = app(JwtService::class)->issue(['sub' => $technician->id])['token'];

        $this->withToken($maintenanceToken)
            ->getJson('/api/v1/maintenance/work-orders')
            ->assertOk()
            ->assertJsonFragment(['id' => $created['id'], 'title' => 'Leaky faucet']);
    }

    public function test_priority_defaults_to_medium(): void
    {
        $unit = $this->unit();

        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/work-orders', [
                'room_unit_id' => $unit->id,
                'title' => 'Squeaky door',
            ])
            ->assertCreated()
            ->assertJsonPath('data.priority', 'medium');
    }

    public function test_the_room_and_title_are_required(): void
    {
        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/work-orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['room_unit_id', 'title']);
    }

    public function test_an_invalid_priority_is_rejected(): void
    {
        $unit = $this->unit();

        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/work-orders', [
                'room_unit_id' => $unit->id,
                'title' => 'Something broken',
                'priority' => 'catastrophic',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('priority');
    }

    public function test_a_non_housekeeping_user_is_forbidden(): void
    {
        $unit = $this->unit();

        $this->withToken($this->otherRoleToken())
            ->postJson('/api/v1/housekeeping/work-orders', [
                'room_unit_id' => $unit->id,
                'title' => 'Something broken',
            ])
            ->assertForbidden();

        $this->assertSame(0, WorkOrder::count());
    }
}
