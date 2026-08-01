<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SecurityNotification;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Security's Notifications feed: listing, marking read, and the one real
 * trigger that exists today — a guest inviting a visitor.
 */
class SecurityNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function officerToken(): string
    {
        Role::findOrCreate('security', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('security');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    public function test_the_feed_is_empty_with_no_notifications(): void
    {
        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_lists_notifications_newest_first_and_unread_by_default(): void
    {
        $older = SecurityNotification::notify('guest', 'Older', 'first');
        $older->forceFill(['created_at' => now()->subHour()])->save();
        SecurityNotification::notify('guest', 'Newer', 'second');

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $notification = SecurityNotification::notify('guest', 'New Visitor Invited', 'Room 101 invited a guest.');

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        SecurityNotification::notify('guest', 'A', 'a');
        SecurityNotification::notify('guest', 'B', 'b');

        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, SecurityNotification::whereNull('read_at')->count());
    }

    public function test_marking_an_unknown_notification_read_is_not_found(): void
    {
        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/notifications/999999/read')
            ->assertNotFound();
    }

    public function test_a_non_security_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/security/notifications')
            ->assertForbidden();
    }

    public function test_a_guest_inviting_a_visitor_notifies_security(): void
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-security-notify',
            'type' => 'suite',
            'price' => 150000,
        ]);
        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => '101', 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 450000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-security-notify']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-SECURITY-NOTIFY-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);
        $deviceToken = $device->createToken('tablet')->plainTextToken;

        $this->withToken($deviceToken)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Michael Brown'])
            ->assertCreated();

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'guest')
            ->assertJsonPath('data.0.title', 'New Visitor Invited')
            ->assertJsonPath('data.0.read', false)
            // Which room and guest this is about, so the officer doesn't
            // have to open the message to find out.
            ->assertJsonPath('data.0.suite_name', 'Alba Suite')
            ->assertJsonPath('data.0.room_number', '101')
            ->assertJsonPath('data.0.guest_name', 'Daniel Ubani');
    }

    public function test_a_notification_without_a_booking_has_no_room_or_guest_info(): void
    {
        SecurityNotification::notify('message', 'Heads up', 'Nothing booking-specific.');

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.suite_name', null)
            ->assertJsonPath('data.0.room_number', null)
            ->assertJsonPath('data.0.guest_name', null);
    }
}
