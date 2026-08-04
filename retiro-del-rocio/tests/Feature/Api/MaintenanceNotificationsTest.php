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

/** The maintenance tablet's notification feed: a new/urgent work order lands, and read state. */
class MaintenanceNotificationsTest extends TestCase
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

    public function test_creating_a_work_order_raises_a_new_work_order_notification(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light', 'priority' => 'low']);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'new_work_order')
            ->assertJsonPath('data.0.read', false);
    }

    public function test_creating_an_urgent_work_order_raises_an_urgent_notification(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Water leak', 'priority' => 'urgent']);

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'urgent_work_order');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light']);

        $id = $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/notifications')
            ->json('data.0.id');

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        $unit = $this->unit();
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Flickering light']);
        WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $this->withToken($this->technicianToken())
            ->postJson('/api/v1/maintenance/notifications/read-all')
            ->assertOk();

        $this->withToken($this->technicianToken())
            ->getJson('/api/v1/maintenance/notifications')
            ->assertJsonPath('data.0.read', true)
            ->assertJsonPath('data.1.read', true);
    }

    public function test_a_non_maintenance_user_cannot_reach_notifications(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/maintenance/notifications')
            ->assertForbidden();
    }
}
