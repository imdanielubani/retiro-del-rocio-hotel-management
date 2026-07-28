<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\GuestNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The in-room tablet's Notifications feed: listing, marking read, and the one
 * real trigger that exists today — a stay extension succeeding.
 */
class TabletNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    private Booking $booking;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-notify',
            'type' => 'suite',
            'price' => 7500,
            'guests' => 4,
            'amenities' => ['High-Speed WiFi'],
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 3,
            'amount' => 37500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-notify']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-NOTIFY-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $device->createToken('tablet')->plainTextToken;
    }

    public function test_the_feed_is_empty_for_a_stay_with_no_notifications(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_lists_notifications_newest_first_and_unread_by_default(): void
    {
        $older = GuestNotification::notify($this->booking, $this->unit, 'message', 'Older', 'first');
        $older->forceFill(['created_at' => now()->subHour()])->save();
        GuestNotification::notify($this->booking, $this->unit, 'payment', 'Newer', 'second');

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $notification = GuestNotification::notify($this->booking, $this->unit, 'spa', 'Spa Appointment', 'Reminder');

        $this->withToken($this->token)
            ->postJson("/api/v1/tablets/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        GuestNotification::notify($this->booking, $this->unit, 'spa', 'A', 'a');
        GuestNotification::notify($this->booking, $this->unit, 'gym', 'B', 'b');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, GuestNotification::where('booking_id', $this->booking->id)->whereNull('read_at')->count());
    }

    public function test_a_guest_cannot_see_or_mark_read_a_notification_from_another_booking(): void
    {
        $otherUnit = RoomUnit::create(['room_id' => $this->room->id, 'number' => '102', 'status' => 'occupied']);
        $otherBooking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'A Previous Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $otherUnit->id,
            'check_in' => now()->subDays(3)->toDateString(),
            'check_out' => now()->subDay()->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'amount' => 15000,
            'status' => 'checked_out',
        ]);
        $foreign = GuestNotification::notify($otherBooking, $otherUnit, 'message', 'Not yours', 'private');

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($this->token)
            ->postJson("/api/v1/tablets/notifications/{$foreign->id}/read")
            ->assertNotFound();
    }

    public function test_extending_the_stay_notifies_the_guest(): void
    {
        Bus::fake();
        config()->set('services.paystack.secret_key', 'sk_test_notify');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 100000000, 'channel' => 'card'],
            ], 200),
        ]);

        $newCheckout = now()->addDays(6)->toDateString();
        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => $newCheckout])
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay', ['check_out' => $newCheckout, 'reference' => $reference])
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'payment')
            ->assertJsonPath('data.0.title', 'Stay Extended')
            ->assertJsonPath('data.0.read', false);
    }
}
