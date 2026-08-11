<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The maintenance tablet's remaining work-order actions: SLA breach
 * tracking, escalate, the manual status override, and technician
 * assignment.
 */
class MaintenanceWorkOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function technicianToken(string $name = 'Alan Turing'): string
    {
        Role::findOrCreate('maintenance', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
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

    public function test_an_open_order_past_its_priority_sla_is_flagged_breached(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $unit = $this->unit();
        // Urgent SLA is 2 hours.
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);

        Carbon::setTestNow('2026-01-01 13:00:00'); // 1h later — within SLA
        $this->assertFalse($order->fresh()->isSlaBreached());

        Carbon::setTestNow('2026-01-01 15:00:00'); // 3h later — past SLA
        $this->assertTrue($order->fresh()->isSlaBreached());

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.sla_breaches', 1)
            ->assertJsonPath('data.work_orders.0.sla_breached', true);
    }

    public function test_a_completed_order_is_never_sla_breached_even_past_its_deadline(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);
        $order->accept();
        $order->start();
        $order->complete();

        Carbon::setTestNow('2026-01-01 20:00:00');
        $this->assertFalse($order->fresh()->isSlaBreached());
    }

    public function test_escalating_bumps_the_priority_up_one_level(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light', 'priority' => 'medium']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/escalate")
            ->assertOk()
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_escalating_an_already_urgent_order_is_a_no_op(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/escalate")
            ->assertOk()
            ->assertJsonPath('data.priority', 'urgent');
    }

    public function test_the_status_override_jumps_straight_to_a_given_status(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertNotNull($order->fresh()->started_at);
    }

    public function test_the_status_override_to_done_notifies_the_guest(): void
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
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'title' => 'AC not cooling']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/status", ['status' => 'done'])
            ->assertOk();

        $this->assertDatabaseHas('guest_notifications', [
            'booking_id' => $booking->id,
            'category' => 'maintenance',
        ]);
    }

    public function test_a_work_order_can_be_assigned_to_a_named_technician(): void
    {
        Role::findOrCreate('maintenance', 'web');
        $technician = User::factory()->create(['status' => 'active', 'name' => 'Grace Hopper']);
        $technician->assignRole('maintenance');

        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/assign", ['technician_id' => $technician->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to_name', 'Grace Hopper');
    }

    public function test_a_work_order_cannot_be_assigned_to_a_non_technician(): void
    {
        $other = User::factory()->create(['status' => 'active']);

        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/assign", ['technician_id' => $other->id])
            ->assertStatus(422);
    }

    public function test_the_technicians_endpoint_lists_only_active_maintenance_staff(): void
    {
        Role::findOrCreate('maintenance', 'web');
        $active = User::factory()->create(['status' => 'active', 'name' => 'Grace Hopper']);
        $active->assignRole('maintenance');
        $inactive = User::factory()->create(['status' => 'inactive', 'name' => 'Inactive Tech']);
        $inactive->assignRole('maintenance');

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/technicians')
            ->assertOk()
            ->assertJsonCount(2, 'data'); // the token's own technician + Grace Hopper
    }
}
