<?php

namespace Tests\Feature\Api;

use App\Events\StaffChatTypingSent;
use App\Models\StaffMessage;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff Chat — every tablet's Chat screen (Reception, Housekeeping,
 * Maintenance, Security) messaging any other station, including the Admin
 * dashboard. One controller, one set of routes, shared by all of them.
 */
class StaffChatTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role, string $name = 'Staffer'): string
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole($role);

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    public function test_channels_lists_every_other_station_including_admin(): void
    {
        $data = $this->withToken($this->tokenFor('maintenance'))
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->json('data');

        $this->assertEqualsCanonicalizing(
            ['reception', 'housekeeping', 'security', 'admin'],
            array_column($data, 'role'),
        );
    }

    public function test_two_departments_can_message_each_other_directly(): void
    {
        $housekeepingToken = $this->tokenFor('housekeeping', 'Ada');
        $maintenanceToken = $this->tokenFor('maintenance', 'Musa');

        $data = $this->withToken($housekeepingToken)
            ->postJson('/api/v1/staff/chat/channels/maintenance/messages', [
                'body' => 'Room 204 needs the AC looked at before we can clean.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_role', 'housekeeping')
            ->assertJsonPath('data.sender_name', 'Ada')
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->assertDatabaseHas('staff_messages', [
            'id' => $data['id'],
            'channel_key' => 'housekeeping_maintenance',
            'sender_role' => 'housekeeping',
        ]);

        // Maintenance reads the same message, from their own side of the channel.
        $this->withToken($maintenanceToken)
            ->getJson('/api/v1/staff/chat/channels/housekeeping/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Room 204 needs the AC looked at before we can clean.')
            ->assertJsonPath('data.0.is_mine', false);
    }

    public function test_a_channel_key_is_the_same_regardless_of_who_started_it(): void
    {
        $this->assertSame(
            StaffMessage::channelKey('maintenance', 'housekeeping'),
            StaffMessage::channelKey('housekeeping', 'maintenance'),
        );
    }

    public function test_a_reply_shows_as_unread_until_the_recipient_opens_the_channel(): void
    {
        $securityToken = $this->tokenFor('security', 'Officer Bello');
        $receptionToken = $this->tokenFor('reception', 'Ada');

        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey('reception', 'security'),
            'sender_role' => 'security',
            'sender_name' => 'Officer Bello',
            'body' => 'Visitor pass for Room 305 has arrived.',
        ]);

        // The channel with a message sorts to the front, ahead of the three
        // still-empty ones.
        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'security')
            ->assertJsonPath('data.0.unread_count', 1);

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels/security/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);

        // Round-trips back through security's own token so both codepaths
        // of the fixture are exercised.
        $this->withToken($securityToken)->getJson('/api/v1/staff/chat/channels')->assertOk();
    }

    public function test_a_channel_reports_online_once_that_role_has_been_active(): void
    {
        $receptionToken = $this->tokenFor('reception');

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'housekeeping')
            ->assertJsonPath('data.0.online', false);

        $housekeepingToken = $this->tokenFor('housekeeping');
        $this->withToken($housekeepingToken)->getJson('/api/v1/staff/chat/channels')->assertOk();

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.online', true);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->withToken($this->tokenFor('reception'))
            ->postJson('/api/v1/staff/chat/channels/kitchen/messages', ['body' => 'Hello?'])
            ->assertStatus(404);
    }

    public function test_a_role_cannot_message_itself(): void
    {
        $this->withToken($this->tokenFor('reception'))
            ->postJson('/api/v1/staff/chat/channels/reception/messages', ['body' => 'Hello?'])
            ->assertStatus(404);
    }

    public function test_a_message_requires_a_body(): void
    {
        $this->withToken($this->tokenFor('reception'))
            ->postJson('/api/v1/staff/chat/channels/maintenance/messages', ['body' => ''])
            ->assertStatus(422);
    }

    public function test_a_non_staff_user_is_forbidden(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertStatus(403);
    }

    public function test_typing_broadcasts_on_the_shared_channel(): void
    {
        Event::fake([StaffChatTypingSent::class]);

        $this->withToken($this->tokenFor('security'))
            ->postJson('/api/v1/staff/chat/channels/maintenance/typing')
            ->assertOk();

        Event::assertDispatched(
            StaffChatTypingSent::class,
            fn (StaffChatTypingSent $e) => $e->channelKey === 'maintenance_security' && $e->from === 'security',
        );
    }
}
