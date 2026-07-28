<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The front desk's Notifications feed: listing, marking read, and the one
 * real trigger that exists today — a guest's stay-extension payment
 * succeeding.
 */
class ReceptionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function receptionToken(): string
    {
        Role::findOrCreate('reception', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');

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
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_lists_notifications_newest_first_and_unread_by_default(): void
    {
        $older = ReceptionNotification::notify('booking', 'Older', 'first');
        $older->forceFill(['created_at' => now()->subHour()])->save();
        ReceptionNotification::notify('payment', 'Newer', 'second');

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $notification = ReceptionNotification::notify('payment', 'Stay Extension Paid', 'Room 101 extended.');

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        ReceptionNotification::notify('booking', 'A', 'a');
        ReceptionNotification::notify('payment', 'B', 'b');

        $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, ReceptionNotification::whereNull('read_at')->count());
    }

    public function test_marking_an_unknown_notification_read_is_not_found(): void
    {
        $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/notifications/999999/read')
            ->assertNotFound();
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/reception/notifications')
            ->assertForbidden();
    }

    public function test_a_guest_paying_to_extend_their_stay_notifies_the_front_desk(): void
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

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-reception-notify',
            'type' => 'suite',
            'price' => 7500,
            'guests' => 4,
            'amenities' => ['High-Speed WiFi'],
        ]);
        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => '101', 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 3,
            'amount' => 37500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-reception-notify']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-RECEPTION-NOTIFY-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);
        $deviceToken = $device->createToken('tablet')->plainTextToken;

        $newCheckout = now()->addDays(6)->toDateString();
        $reference = $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => $newCheckout])
            ->json('data.reference');

        $this->withToken($deviceToken)
            ->postJson('/api/v1/tablets/extend-stay', ['check_out' => $newCheckout, 'reference' => $reference])
            ->assertOk();

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'payment')
            ->assertJsonPath('data.0.title', 'Stay Extension')
            ->assertJsonPath('data.0.read', false);
    }
}
