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
 * The maintenance tablet: the dashboard and the work-order board.
 */
class MaintenanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function technicianToken(): string
    {
        Role::findOrCreate('maintenance', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Alan Turing']);
        $user->assignRole('maintenance');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');

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

    public function test_overview_reports_stats_and_sorts_urgent_first(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling', 'priority' => 'low']);
        $urgent = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.new', 2)
            ->assertJsonPath('data.stats.urgent', 1)
            ->assertJsonPath('data.work_orders.0.id', $urgent->id)
            ->assertJsonPath('data.work_orders.0.priority_label', 'Urgent');
    }

    public function test_a_completed_order_does_not_appear_in_the_open_overview_list(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);
        $order->accept();
        $order->start();
        $order->complete();

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/overview')
            ->assertOk()
            ->assertJsonCount(0, 'data.work_orders')
            ->assertJsonPath('data.stats.completed_today', 1);
    }

    public function test_work_orders_lists_every_order_and_filters_by_status_and_priority(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'A', 'priority' => 'high']);
        $accepted = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'B', 'priority' => 'low']);
        $accepted->accept();

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/work-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/work-orders?status=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'B');

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/work-orders?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'A');
    }

    public function test_a_work_order_can_be_reported_against_an_asset_with_no_room(): void
    {
        $data = $this->withToken($this->technicianToken())
            ->postJson('/api/v1/maintenance/work-orders', [
                'asset_label' => 'Lobby generator',
                'title' => 'Generator won\'t start',
                'priority' => 'urgent',
                'reported_by' => 'Front desk',
            ])
            ->assertCreated()
            ->assertJsonPath('data.location_label', 'Lobby generator')
            ->assertJsonPath('data.status', 'new')
            ->json('data');

        $this->assertDatabaseHas('work_orders', ['id' => $data['id'], 'room_unit_id' => null]);
    }

    public function test_the_full_lifecycle_from_new_to_done(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Leaky faucet']);
        $token = $this->technicianToken();

        $this->withToken($token)
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->withToken($token)
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->withToken($token)
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->accepted_at);
        $this->assertNotNull($fresh->started_at);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_completing_a_guests_fault_notifies_their_tablet(): void
    {
        $unit = $this->unit();
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Grace Hopper',
            'room_id' => $unit->room_id,
            'room_name' => 'Brisa Residence',
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $order = WorkOrder::create([
            'room_unit_id' => $unit->id,
            'booking_id' => $booking->id,
            'title' => 'AC not cooling',
        ]);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('guest_notifications', [
            'booking_id' => $booking->id,
            'room_unit_id' => $unit->id,
            'category' => 'maintenance',
            'title' => 'Maintenance Request Completed',
            'message' => 'Your AC not cooling request has been completed.',
        ]);
    }

    public function test_completing_a_staff_reported_fault_does_not_notify_a_guest(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Lobby light out']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/complete")
            ->assertOk();

        $this->assertDatabaseCount('guest_notifications', 0);
    }

    public function test_starting_an_order_that_was_never_accepted_is_rejected(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'new'); // no-op: still new

        $this->assertNull($order->fresh()->started_at);
    }

    public function test_rooms_returns_the_room_picker(): void
    {
        $this->unit();
        $this->unit();

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/rooms')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_non_maintenance_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/maintenance/overview')
            ->assertForbidden();
    }
}
