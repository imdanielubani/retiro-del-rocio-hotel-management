<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Maintenance\Assets;
use App\Livewire\Admin\Maintenance\PartsRequests;
use App\Livewire\Admin\Maintenance\WorkOrders;
use App\Models\Asset as AssetModel;
use App\Models\PartsRequest;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Maintenance — the desk's view of the maintenance tablet's Work
 * Orders board, Assets tab and Requests (parts) tab.
 */
class MaintenanceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function unit(array $overrides = []): RoomUnit
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-mt-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(array_merge([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_work_orders_lists_every_order_and_filters_by_status(): void
    {
        $unit = $this->unit(['number' => '101']);
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);
        $accepted = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light']);
        $accepted->accept();

        Livewire::actingAs($this->admin())
            ->test(WorkOrders::class)
            ->assertOk()
            ->assertSee('Water leak')
            ->assertSee('Flickering light')
            ->set('statusFilter', 'accepted')
            ->assertSee('Flickering light')
            ->assertDontSee('Water leak');
    }

    public function test_work_orders_search_narrows_by_title(): void
    {
        $unit = $this->unit(['number' => '102']);
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak']);
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        Livewire::actingAs($this->admin())
            ->test(WorkOrders::class)
            ->set('search', 'Water')
            ->assertSee('Water leak')
            ->assertDontSee('Broken lamp');
    }

    public function test_assets_lists_every_asset_and_flags_service_due(): void
    {
        AssetModel::create(['name' => 'Lobby Generator', 'service_interval_days' => 30, 'last_serviced_at' => now()->subDays(40)]);
        AssetModel::create(['name' => 'Pool Pump', 'service_interval_days' => 30, 'last_serviced_at' => now()->subDays(5)]);

        Livewire::actingAs($this->admin())
            ->test(Assets::class)
            ->assertOk()
            ->assertSee('Lobby Generator')
            ->assertSee('Pool Pump')
            ->assertSee('Service Due')
            ->set('dueOnly', true)
            ->assertSee('Lobby Generator')
            ->assertDontSee('Pool Pump');
    }

    public function test_marking_an_asset_serviced_from_the_admin_restarts_its_interval(): void
    {
        $asset = AssetModel::create(['name' => 'Lobby Generator', 'service_interval_days' => 30, 'last_serviced_at' => now()->subDays(40)]);

        Livewire::actingAs($this->admin())
            ->test(Assets::class)
            ->call('markServiced', $asset->id);

        $this->assertNotNull($asset->fresh()->last_serviced_at);
        $this->assertFalse($asset->fresh()->isDueForService());
    }

    public function test_parts_requests_lists_every_request_and_filters_by_status(): void
    {
        $unit = $this->unit(['number' => '103']);
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $pending = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);
        $fulfilled = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Belt']);
        $fulfilled->fulfill();

        Livewire::actingAs($this->admin())
            ->test(PartsRequests::class)
            ->assertOk()
            ->assertSee('Filter')
            ->assertSee('Belt')
            ->set('statusFilter', 'pending')
            ->assertSee('Filter')
            ->assertDontSee('Belt');

        $this->assertTrue($pending->fresh()->isPending());
    }

    public function test_fulfilling_a_pending_request_from_the_admin_marks_it_fulfilled(): void
    {
        $unit = $this->unit(['number' => '104']);
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $request = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);

        Livewire::actingAs($this->admin())
            ->test(PartsRequests::class)
            ->call('fulfill', $request->id);

        $this->assertSame('fulfilled', $request->fresh()->status);
    }

    public function test_denying_a_pending_request_from_the_admin_marks_it_denied(): void
    {
        $unit = $this->unit(['number' => '105']);
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $request = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);

        Livewire::actingAs($this->admin())
            ->test(PartsRequests::class)
            ->call('deny', $request->id);

        $this->assertSame('denied', $request->fresh()->status);
    }
}
