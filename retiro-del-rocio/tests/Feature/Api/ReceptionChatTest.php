<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\StaffMessage;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reception's Chat screen — Concierge Chat threads with every in-house
 * guest, and internal channels with each staff department.
 */
class ReceptionChatTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-chat',
            'type' => 'suite',
            'price' => 150000,
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
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);
    }

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Front Desk Ada']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('maintenance');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('maintenance');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    /* ---------------------------------------------------------------
     | Guest conversations
     |------------------------------------------------------------- */

    public function test_guest_conversations_lists_every_checked_in_booking(): void
    {
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/chat/guests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_id', $this->booking->id)
            ->assertJsonPath('data.0.guest_name', 'Daniel Ubani')
            ->assertJsonPath('data.0.room_label', 'Brisa Residence · Room 101')
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_a_guest_message_shows_as_unread_until_reception_opens_the_thread(): void
    {
        ChatMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => ChatMessage::GUEST,
            'sender_name' => 'Daniel Ubani',
            'body' => 'Hi, can I get a late checkout?',
        ]);

        $token = $this->receptionToken();

        $this->withToken($token)
            ->getJson('/api/v1/reception/chat/guests')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.last_message', 'Hi, can I get a late checkout?');

        $this->withToken($token)
            ->getJson("/api/v1/reception/chat/guests/{$this->booking->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_mine', false);

        $this->withToken($token)
            ->getJson('/api/v1/reception/chat/guests')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_reception_replies_to_a_guest_and_it_notifies_their_tablet(): void
    {
        $data = $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/chat/guests/{$this->booking->id}/messages", [
                'body' => 'Late checkout is confirmed for 2pm.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'staff')
            ->assertJsonPath('data.sender_name', 'Front Desk Ada')
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->assertDatabaseHas('chat_messages', [
            'id' => $data['id'],
            'booking_id' => $this->booking->id,
            'sender_type' => 'staff',
        ]);

        $this->assertDatabaseHas('guest_notifications', [
            'booking_id' => $this->booking->id,
            'room_unit_id' => $this->unit->id,
            'category' => 'message',
        ]);
    }

    public function test_a_non_reception_user_cannot_reach_guest_chat(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/reception/chat/guests')
            ->assertStatus(403);
    }

    public function test_a_checked_out_booking_cannot_be_messaged(): void
    {
        $this->booking->update(['status' => 'checked_out']);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/chat/guests/{$this->booking->id}/messages", ['body' => 'Hello?'])
            ->assertStatus(409);
    }

    /* ---------------------------------------------------------------
     | Staff department channels
     |------------------------------------------------------------- */

    public function test_staff_conversations_lists_every_department(): void
    {
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/chat/staff')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.department', 'housekeeping')
            ->assertJsonPath('data.1.department', 'maintenance')
            ->assertJsonPath('data.2.department', 'security');
    }

    public function test_reception_sends_a_message_to_a_department(): void
    {
        $data = $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/chat/staff/maintenance/messages', [
                'body' => 'Room 204 AC still not fixed?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_role', 'reception')
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->assertDatabaseHas('staff_messages', [
            'id' => $data['id'],
            'department' => 'maintenance',
            'sender_role' => 'reception',
        ]);
    }

    public function test_an_unknown_department_is_rejected(): void
    {
        $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/chat/staff/kitchen/messages', ['body' => 'Hello?'])
            ->assertStatus(404);
    }

    public function test_a_department_reply_shows_as_unread_until_reception_opens_the_channel(): void
    {
        StaffMessage::create([
            'department' => 'security',
            'sender_role' => 'security',
            'sender_name' => 'Officer Musa',
            'body' => 'Visitor pass for Room 305 has arrived.',
        ]);

        $token = $this->receptionToken();

        $this->withToken($token)
            ->getJson('/api/v1/reception/chat/staff')
            ->assertOk()
            ->assertJsonPath('data.2.department', 'security')
            ->assertJsonPath('data.2.unread_count', 1);

        $this->withToken($token)
            ->getJson('/api/v1/reception/chat/staff/security/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_mine', false);

        $this->withToken($token)
            ->getJson('/api/v1/reception/chat/staff')
            ->assertOk()
            ->assertJsonPath('data.2.unread_count', 0);
    }
}
