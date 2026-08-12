<?php

namespace Tests\Feature\Api;

use App\Events\StaffChatMessageSent;
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
 * Maintenance, Security, Bar, Kitchen) messaging any other *individual*
 * staff member, including admin-portal users. One controller, one set of
 * routes, shared by all of them — a channel is a pair of user IDs, so two
 * accounts holding the same role are reachable separately.
 */
class StaffChatTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function userFor(string $role, string $name = 'Staffer'): array
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole($role);

        return [$user, app(JwtService::class)->issue(['sub' => $user->id])['token']];
    }

    private function tokenFor(string $role, string $name = 'Staffer'): string
    {
        return $this->userFor($role, $name)[1];
    }

    public function test_channels_lists_every_other_staff_member_including_admin(): void
    {
        $this->userFor('reception');
        $this->userFor('housekeeping');
        $this->userFor('security');
        $this->userFor('bar');
        $this->userFor('kitchen');
        Role::findOrCreate('manager');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('manager');

        [, $token] = $this->userFor('maintenance');

        $data = $this->withToken($token)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->json('data');

        $this->assertEqualsCanonicalizing(
            ['reception', 'housekeeping', 'security', 'bar', 'kitchen', 'admin'],
            array_column($data, 'role'),
        );
    }

    public function test_two_individual_staff_members_can_message_each_other_directly(): void
    {
        [$housekeeper, $housekeepingToken] = $this->userFor('housekeeping', 'Ada');
        [$maintainer, $maintenanceToken] = $this->userFor('maintenance', 'Musa');

        $data = $this->withToken($housekeepingToken)
            ->postJson("/api/v1/staff/chat/channels/{$maintainer->id}/messages", [
                'body' => 'Room 204 needs the AC looked at before we can clean.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender_role', 'housekeeping')
            ->assertJsonPath('data.sender_name', 'Ada')
            ->assertJsonPath('data.is_mine', true)
            ->json('data');

        $this->assertDatabaseHas('staff_messages', [
            'id' => $data['id'],
            'channel_key' => StaffMessage::channelKey($housekeeper->id, $maintainer->id),
            'sender_id' => $housekeeper->id,
            'recipient_id' => $maintainer->id,
            'sender_role' => 'housekeeping',
        ]);

        // Maintenance reads the same message, from their own side of the channel.
        $this->withToken($maintenanceToken)
            ->getJson("/api/v1/staff/chat/channels/{$housekeeper->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Room 204 needs the AC looked at before we can clean.')
            ->assertJsonPath('data.0.is_mine', false);
    }

    public function test_two_accounts_holding_the_same_role_get_separate_threads(): void
    {
        [$bar1, $bar1Token] = $this->userFor('bar', 'Bar 1');
        [$bar2] = $this->userFor('bar', 'Bar 2');
        [$kitchen, $kitchenToken] = $this->userFor('kitchen', 'Amara Chef');

        // Kitchen messages Bar 1 specifically.
        $this->withToken($kitchenToken)
            ->postJson("/api/v1/staff/chat/channels/{$bar1->id}/messages", ['body' => 'Table 4 order is ready.'])
            ->assertCreated();

        // Bar 1 sees it and it's unread for them; Bar 2 has no such message at all.
        $bar1Channels = $this->withToken($bar1Token)->getJson('/api/v1/staff/chat/channels')->json('data');
        $kitchenRow = collect($bar1Channels)->firstWhere('user_id', $kitchen->id);
        $this->assertSame(1, $kitchenRow['unread_count']);

        $bar2Token = app(JwtService::class)->issue(['sub' => $bar2->id])['token'];
        $bar2Channels = $this->withToken($bar2Token)->getJson('/api/v1/staff/chat/channels')->json('data');
        $kitchenRowForBar2 = collect($bar2Channels)->firstWhere('user_id', $kitchen->id);
        $this->assertSame(0, $kitchenRowForBar2['unread_count']);
        $this->assertNull($kitchenRowForBar2['last_message']);
    }

    public function test_a_channel_key_is_the_same_regardless_of_who_started_it(): void
    {
        $this->assertSame(
            StaffMessage::channelKey(4, 9),
            StaffMessage::channelKey(9, 4),
        );
    }

    public function test_a_reply_shows_as_unread_until_the_recipient_opens_the_channel(): void
    {
        [$officer, $securityToken] = $this->userFor('security', 'Officer Bello');
        [$receptionist, $receptionToken] = $this->userFor('reception', 'Ada');

        StaffMessage::create([
            'channel_key' => StaffMessage::channelKey($receptionist->id, $officer->id),
            'sender_id' => $officer->id,
            'recipient_id' => $receptionist->id,
            'sender_role' => 'security',
            'sender_name' => 'Officer Bello',
            'body' => 'Visitor pass for Room 305 has arrived.',
        ]);

        // The channel with a message sorts to the front, ahead of the empty ones.
        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'security')
            ->assertJsonPath('data.0.unread_count', 1);

        $this->withToken($receptionToken)
            ->getJson("/api/v1/staff/chat/channels/{$officer->id}/messages")
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

    public function test_a_channel_reports_online_once_that_person_has_been_active(): void
    {
        [, $receptionToken] = $this->userFor('reception');
        [, $housekeepingToken] = $this->userFor('housekeeping');

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'housekeeping')
            ->assertJsonPath('data.0.online', false);

        $this->withToken($housekeepingToken)->getJson('/api/v1/staff/chat/channels')->assertOk();

        $this->withToken($receptionToken)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->assertJsonPath('data.0.online', true);
    }

    public function test_the_manager_channel_is_labelled_and_reports_online_for_any_admin_portal_role(): void
    {
        // The admin dashboard's "Manager" seat can be staffed by any of the
        // admin-portal roles (see User::isAdmin()), not just one literally
        // named "admin" — a user signed in as plain "manager" should still
        // show up, individually, as an online Manager contact.
        Role::findOrCreate('manager');
        $manager = User::factory()->create(['status' => 'active', 'name' => 'Grace Manager']);
        $manager->assignRole('manager');
        $manager->forceFill(['last_seen_at' => now()])->saveQuietly();

        $data = $this->withToken($this->tokenFor('reception'))
            ->getJson('/api/v1/staff/chat/channels')
            ->assertOk()
            ->json('data');

        $admin = collect($data)->firstWhere('user_id', $manager->id);
        $this->assertNotNull($admin);
        $this->assertSame('Manager', $admin['role_label']);
        $this->assertSame('Grace Manager', $admin['name']);
        $this->assertTrue($admin['online']);
    }

    public function test_an_unknown_contact_is_rejected(): void
    {
        $this->withToken($this->tokenFor('reception'))
            ->postJson('/api/v1/staff/chat/channels/999999/messages', ['body' => 'Hello?'])
            ->assertStatus(404);
    }

    public function test_a_staff_member_cannot_message_themselves(): void
    {
        [$user, $token] = $this->userFor('reception');

        $this->withToken($token)
            ->postJson("/api/v1/staff/chat/channels/{$user->id}/messages", ['body' => 'Hello?'])
            ->assertStatus(404);
    }

    public function test_a_message_requires_a_body(): void
    {
        [$maintainer] = $this->userFor('maintenance');

        $this->withToken($this->tokenFor('reception'))
            ->postJson("/api/v1/staff/chat/channels/{$maintainer->id}/messages", ['body' => ''])
            ->assertStatus(422);
    }

    public function test_a_non_staff_user_is_forbidden(): void
    {
        Role::findOrCreate('valet');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('valet');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/staff/chat/channels')
            ->assertStatus(403);
    }

    public function test_typing_broadcasts_on_the_shared_channel(): void
    {
        Event::fake([StaffChatTypingSent::class]);

        [$officer, $securityToken] = $this->userFor('security');
        [$maintainer] = $this->userFor('maintenance');

        $this->withToken($securityToken)
            ->postJson("/api/v1/staff/chat/channels/{$maintainer->id}/typing")
            ->assertOk();

        Event::assertDispatched(
            StaffChatTypingSent::class,
            fn (StaffChatTypingSent $e) => $e->channelKey === StaffMessage::channelKey($officer->id, $maintainer->id)
                && $e->from === $officer->id,
        );
    }

    public function test_sending_a_message_broadcasts_to_the_recipients_own_inbox(): void
    {
        Event::fake([StaffChatMessageSent::class]);

        [$officer, $securityToken] = $this->userFor('security');
        [$maintainer] = $this->userFor('maintenance');

        $this->withToken($securityToken)
            ->postJson("/api/v1/staff/chat/channels/{$maintainer->id}/messages", [
                'body' => 'Camera 4 in the east wing is down.',
            ])
            ->assertCreated();

        Event::assertDispatched(
            StaffChatMessageSent::class,
            fn (StaffChatMessageSent $e) => $e->toUserId === $maintainer->id && $e->fromUserId === $officer->id,
        );
    }
}
