<?php

namespace Tests\Feature\Api;

use App\Models\PartsRequest;
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
 * The maintenance tablet's Requests tab: parts a technician asked for
 * against a work order, and the fulfil/deny actions on them.
 */
class MaintenancePartsRequestsTest extends TestCase
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

    public function test_a_parts_request_can_be_raised_against_a_work_order(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/parts-requests", [
                'part_name' => 'Compressor capacitor',
                'quantity' => 2,
                'note' => 'Burnt out, needs replacing',
            ])
            ->assertCreated()
            ->assertJsonPath('data.part_name', 'Compressor capacitor')
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requested_by', 'Alan Turing')
            ->assertJsonPath('data.location_label', 'Room '.$unit->number);
    }

    public function test_the_requests_tab_lists_every_request_newest_first_and_filters_by_status(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $first = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);
        $second = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Belt']);
        $second->fulfill();

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/parts-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/parts-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);
    }

    public function test_a_pending_request_can_be_fulfilled(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $partsRequest = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/parts-requests/{$partsRequest->id}/fulfill")
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');

        $this->assertNotNull($partsRequest->fresh()->fulfilled_at);
    }

    public function test_a_pending_request_can_be_denied(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $partsRequest = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/parts-requests/{$partsRequest->id}/deny")
            ->assertOk()
            ->assertJsonPath('data.status', 'denied');
    }

    public function test_an_already_fulfilled_request_cannot_be_denied(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);
        $partsRequest = PartsRequest::create(['work_order_id' => $order->id, 'part_name' => 'Filter']);
        $partsRequest->fulfill();

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/parts-requests/{$partsRequest->id}/deny")
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');
    }

    public function test_a_non_maintenance_user_cannot_reach_parts_requests(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/maintenance/parts-requests')
            ->assertForbidden();
    }
}
