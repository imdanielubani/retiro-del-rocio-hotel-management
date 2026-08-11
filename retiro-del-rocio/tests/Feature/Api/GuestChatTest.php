<?php

namespace Tests\Feature\Api;

use App\Events\ChatTypingSent;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Concierge Chat — a guest's in-room tablet messaging the front desk.
 */
class GuestChatTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private Booking $booking;

    private Device $device;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-chat',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 450000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-chat']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-CHAT-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $this->device->createToken('tablet')->plainTextToken;
    }

    public function test_a_guest_sends_a_message_and_it_notifies_reception(): void
    {
        $data = $this->withToken($this->token)
            ->postJson('/api/v1/chat/messages', ['body' => 'Hi! Is it possible to get a late checkout?'])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'guest')
            ->assertJsonPath('data.sender_name', 'Daniel Ubani')
            ->assertJsonPath('data.body', 'Hi! Is it possible to get a late checkout?')
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->assertDatabaseHas('chat_messages', [
            'id' => $data['id'],
            'booking_id' => $this->booking->id,
            'sender_type' => 'guest',
        ]);

        $this->assertDatabaseHas('reception_notifications', [
            'booking_id' => $this->booking->id,
            'category' => 'message',
        ]);
    }

    public function test_a_message_requires_a_body(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/chat/messages', ['body' => ''])
            ->assertStatus(422);
    }

    public function test_the_thread_lists_every_message_oldest_first(): void
    {
        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::STAFF,
            'sender_name' => 'Reception',
            'body' => 'Welcome, Mr. Anderson! How can we assist you today?',
            'created_at' => now()->subMinute(),
        ]);

        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::GUEST,
            'sender_name' => 'Daniel Ubani',
            'body' => 'Hi! Is it possible to get a late checkout on July 14th?',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Welcome, Mr. Anderson! How can we assist you today?')
            ->assertJsonPath('data.0.is_mine', false)
            ->assertJsonPath('data.1.body', 'Hi! Is it possible to get a late checkout on July 14th?')
            ->assertJsonPath('data.1.is_mine', true);
    }

    public function test_opening_the_thread_reports_and_then_clears_the_unread_count(): void
    {
        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::STAFF,
            'sender_name' => 'Reception',
            'body' => 'Your late checkout is confirmed.',
        ]);
        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::STAFF,
            'sender_name' => 'Reception',
            'body' => 'Anything else we can help with?',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, ChatMessage::where('booking_id', $this->booking->id)->whereNull('read_at')->count());
    }

    public function test_the_next_guest_never_sees_the_previous_guests_chat_history(): void
    {
        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::GUEST,
            'sender_name' => 'Daniel Ubani',
            'body' => 'A message from the previous stay.',
        ]);

        $this->booking->update(['status' => 'checked_out']);
        $this->unit->update(['booking_id' => null, 'status' => 'available']);

        $newBooking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'New Guest',
            'room_id' => $this->unit->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $this->unit->update(['booking_id' => $newBooking->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reception_online_is_false_until_a_receptionist_has_been_recently_active(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonPath('reception_online', false);

        // Assigned via the raw pivot relationship rather than assignRole():
        // Spatie's role cache can already hold an empty snapshot from the
        // chat call above, and assignRole() trusts that cache to resolve the
        // role it just created.
        $role = Role::create(['name' => 'reception', 'guard_name' => 'web']);
        $receptionist = User::factory()->create(['status' => 'active']);
        $receptionist->roles()->attach($role->id);
        $staffToken = app(JwtService::class)->issue(['sub' => $receptionist->id])['token'];

        // Any authenticated reception call stamps presence — a plain guest
        // list is enough, the guest side never sees which endpoint it was.
        $this->withToken($staffToken)->getJson('/api/v1/reception/chat/guests')->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/chat/messages')
            ->assertOk()
            ->assertJsonPath('reception_online', true);
    }

    public function test_typing_broadcasts_on_the_rooms_channel(): void
    {
        Event::fake([ChatTypingSent::class]);

        $this->withToken($this->token)->postJson('/api/v1/chat/typing')->assertOk();

        Event::assertDispatched(
            ChatTypingSent::class,
            fn (ChatTypingSent $e) => $e->roomUnitId === $this->unit->id && $e->from === ChatMessage::GUEST,
        );
    }
}
