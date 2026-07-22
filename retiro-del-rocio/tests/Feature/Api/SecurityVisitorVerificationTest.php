<?php

namespace Tests\Feature\Api;

use App\Models\RoomUnit;
use App\Models\Room;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The security tablet's Visitor Verification: listing today's passes, looking one
 * up by the code the visitor quotes, and admitting them.
 */
class SecurityVisitorVerificationTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-sv',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);
    }

    private function officerToken(): string
    {
        Role::findOrCreate('security');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Daniel Ubani']);
        $user->assignRole('security');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function pass(array $overrides = []): VisitorPass
    {
        return VisitorPass::create(array_merge([
            'room_unit_id' => $this->unit->id,
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'visitor_name' => 'Michael Brown',
            'visitor_email' => 'm.brown@mail.com',
            'visitor_phone' => '+44 7700 900111',
            'code' => '482913',
            'status' => VisitorPass::PENDING,
        ], $overrides));
    }

    public function test_it_lists_todays_visitor_passes(): void
    {
        $this->pass();

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/visitors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.visitor_name', 'Michael Brown')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.code', '482913');
    }

    public function test_verifying_a_valid_code_returns_the_pass(): void
    {
        $this->pass(['code' => '482913']);

        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => '482913'])
            ->assertOk()
            ->assertJsonPath('data.visitor_name', 'Michael Brown')
            ->assertJsonPath('data.host_name', 'Daniel Ubani')
            ->assertJsonPath('data.room_number', '101');
    }

    public function test_an_unknown_code_is_not_recognised(): void
    {
        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => '000000'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Code not recognised.');
    }

    public function test_a_spent_code_reads_as_already_used(): void
    {
        $this->pass(['code' => '482913', 'status' => VisitorPass::VERIFIED, 'verified_at' => now()]);

        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => '482913'])
            ->assertNotFound()
            ->assertJsonPath('message', 'This code has already been used.');
    }

    public function test_granting_access_verifies_the_pass(): void
    {
        $pass = $this->pass();
        $token = $this->officerToken();

        $this->withToken($token)
            ->postJson("/api/v1/security/visitors/{$pass->id}/grant")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('visitor_passes', [
            'id' => $pass->id,
            'status' => 'verified',
        ]);
        $this->assertNotNull($pass->fresh()->verified_at);
        $this->assertNotNull($pass->fresh()->handled_by);
    }

    public function test_a_granted_pass_can_no_longer_be_verified_by_code(): void
    {
        $pass = $this->pass(['code' => '482913']);
        $token = $this->officerToken();

        $this->withToken($token)->postJson("/api/v1/security/visitors/{$pass->id}/grant")->assertOk();

        // The code is now spent — quoting it again must not re-admit.
        $this->withToken($token)
            ->postJson('/api/v1/security/visitors/verify', ['code' => '482913'])
            ->assertNotFound();
    }

    public function test_verified_passes_feed_the_dashboard_counters(): void
    {
        $this->pass(['code' => '111111', 'status' => VisitorPass::VERIFIED, 'verified_at' => now()]);
        $this->pass(['code' => '222222', 'visitor_name' => 'Zara Ahmed']); // pending

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.visitors_today', 2)
            ->assertJsonPath('data.stats.verified_passes', 1)
            ->assertJsonCount(1, 'data.visitors')
            ->assertJsonCount(1, 'data.pass_requests');
    }

    public function test_denying_turns_the_visitor_away(): void
    {
        $pass = $this->pass();

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/visitors/{$pass->id}/deny")
            ->assertOk()
            ->assertJsonPath('data.status', 'denied');

        $this->assertDatabaseHas('visitor_passes', ['id' => $pass->id, 'status' => 'denied']);
        $this->assertNotNull($pass->fresh()->denied_at);

        // A denied code no longer verifies.
        $this->withToken($this->officerToken())
            ->postJson('/api/v1/security/visitors/verify', ['code' => $pass->code])
            ->assertNotFound();
    }

    public function test_a_non_security_user_cannot_verify(): void
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->postJson('/api/v1/security/visitors/verify', ['code' => '482913'])
            ->assertForbidden();
    }
}
