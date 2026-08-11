<?php

namespace Tests\Feature\Api;

use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The maintenance tablet's Work Order detail screen: the order plus its
 * photo/video attachments.
 */
class MaintenanceWorkOrderAttachmentsTest extends TestCase
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

    public function test_the_detail_endpoint_returns_the_order_with_an_empty_attachment_list(): void
    {
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);

        $this->withToken($this->technicianToken())
            ->getJson("/api/v1/maintenance/work-orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(0, 'data.attachments');
    }

    public function test_a_photo_can_be_attached_to_a_work_order(): void
    {
        Storage::fake('public');
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'AC not cooling']);

        $photo = UploadedFile::fake()->image('fault.jpg', 800, 600);

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/attachments", ['file' => $photo])
            ->assertCreated()
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.type', 'photo')
            ->assertJsonPath('data.attachments.0.uploaded_by', 'Alan Turing');

        $this->assertDatabaseHas('work_order_attachments', ['work_order_id' => $order->id, 'type' => 'photo']);
    }

    public function test_a_video_can_be_attached_to_a_work_order(): void
    {
        Storage::fake('public');
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Elevator noise']);

        $video = UploadedFile::fake()->create('proof.mp4', 2000, 'video/mp4');

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/attachments", ['file' => $video])
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.type', 'video');
    }

    public function test_an_attachment_with_a_disallowed_type_is_rejected(): void
    {
        Storage::fake('public');
        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $bad = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $this->withToken($this->technicianToken())
            ->postJson("/api/v1/maintenance/work-orders/{$order->id}/attachments", ['file' => $bad])
            ->assertStatus(422);
    }

    public function test_a_non_maintenance_user_cannot_view_a_work_order_detail(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $unit = $this->unit();
        $order = WorkOrder::create(['room_unit_id' => $unit->id, 'title' => 'Broken lamp']);

        $this->withToken($token)
            ->getJson("/api/v1/maintenance/work-orders/{$order->id}")
            ->assertForbidden();
    }
}
