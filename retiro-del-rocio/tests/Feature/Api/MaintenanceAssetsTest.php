<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
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
 * The maintenance tablet's Assets tab: the registry, service history, and
 * the preventive-maintenance due list.
 */
class MaintenanceAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function technicianToken(): string
    {
        Role::findOrCreate('maintenance', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Alan Turing']);
        $user->assignRole('maintenance');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function unit(): RoomUnit
    {
        $room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-mt-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ]);
    }

    public function test_an_asset_can_be_registered_and_appears_in_the_list(): void
    {
        $unit = $this->unit();

        $this->withToken($this->technicianToken())
            ->postJson('/api/v1/maintenance/assets', [
                'name' => 'Room AC Unit',
                'category' => 'HVAC',
                'room_unit_id' => $unit->id,
                'service_interval_days' => 90,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Room AC Unit')
            ->assertJsonPath('data.location_label', 'Room '.$unit->number)
            ->assertJsonPath('data.is_due_for_service', false);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/assets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Room AC Unit');
    }

    public function test_an_asset_with_no_service_interval_is_never_due(): void
    {
        Asset::create(['name' => 'Lobby Chandelier']);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/assets?due=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_asset_past_its_service_interval_is_flagged_due(): void
    {
        Asset::create([
            'name' => 'Lobby Generator',
            'service_interval_days' => 30,
            'last_serviced_at' => now()->subDays(40),
        ]);
        Asset::create([
            'name' => 'Pool Pump',
            'service_interval_days' => 30,
            'last_serviced_at' => now()->subDays(5),
        ]);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/assets?due=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Lobby Generator');
    }

    public function test_marking_an_asset_serviced_restarts_its_interval(): void
    {
        $asset = Asset::create([
            'name' => 'Lobby Generator',
            'service_interval_days' => 30,
            'last_serviced_at' => now()->subDays(40),
        ]);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/assets/{$asset->id}/mark-serviced")
            ->assertOk()
            ->assertJsonPath('data.is_due_for_service', false);

        $this->assertNotNull($asset->fresh()->last_serviced_at);
    }

    public function test_asset_detail_lists_its_service_history(): void
    {
        $unit = $this->unit();
        $asset = Asset::create(['name' => 'Room AC Unit', 'room_unit_id' => $unit->id]);
        WorkOrder::create(['room_unit_id' => $unit->id, 'asset_id' => $asset->id, 'title' => 'AC not cooling']);
        WorkOrder::create(['room_unit_id' => $unit->id, 'asset_id' => $asset->id, 'title' => 'AC leaking water']);

        $this->withToken($this->technicianToken())
            ->getJson("/api/v1/maintenance/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Room AC Unit')
            ->assertJsonCount(2, 'data.service_history');
    }

    public function test_a_work_order_reported_against_a_registered_asset_carries_its_name(): void
    {
        $asset = Asset::create(['name' => 'Lobby Generator']);

        $this->withToken($this->technicianToken())
            ->postJson('/api/v1/maintenance/work-orders', [
                'asset_id' => $asset->id,
                'title' => 'Generator won\'t start',
            ])
            ->assertCreated()
            ->assertJsonPath('data.asset_label', 'Lobby Generator')
            ->assertJsonPath('data.location_label', 'Lobby Generator');
    }

    public function test_a_non_maintenance_user_cannot_reach_assets(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/maintenance/assets')
            ->assertForbidden();
    }
}
