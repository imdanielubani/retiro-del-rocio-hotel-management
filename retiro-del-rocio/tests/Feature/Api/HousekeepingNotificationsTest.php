<?php

namespace Tests\Feature\Api;

use App\Models\HousekeepingNotification;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Housekeeping's Notifications feed: listing, marking read, and the one
 * real trigger that exists today — a guest raising a housekeeping request.
 */
class HousekeepingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function housekeeperToken(): string
    {
        Role::findOrCreate('housekeeping', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('housekeeping');

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
        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_feed_lists_notifications_newest_first_and_unread_by_default(): void
    {
        $older = HousekeepingNotification::notify('guest', 'Older', 'first');
        $older->forceFill(['created_at' => now()->subHour()])->save();
        HousekeepingNotification::notify('guest', 'Newer', 'second');

        $this->withToken($this->housekeeperToken())
            ->getJson('/api/v1/housekeeping/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.1.title', 'Older');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $notification = HousekeepingNotification::notify('guest', 'New Housekeeping Request', 'Room 101 requested towels.');

        $this->withToken($this->housekeeperToken())
            ->postJson("/api/v1/housekeeping/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification(): void
    {
        HousekeepingNotification::notify('guest', 'A', 'a');
        HousekeepingNotification::notify('guest', 'B', 'b');

        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, HousekeepingNotification::whereNull('read_at')->count());
    }

    public function test_marking_an_unknown_notification_read_is_not_found(): void
    {
        $this->withToken($this->housekeeperToken())
            ->postJson('/api/v1/housekeeping/notifications/999999/read')
            ->assertNotFound();
    }

    public function test_a_non_housekeeping_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/housekeeping/notifications')
            ->assertForbidden();
    }
}
