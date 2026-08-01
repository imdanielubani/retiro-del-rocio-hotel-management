<?php

namespace Tests\Feature\Api;

use App\Models\Room;
use App\Models\RoomUnit;
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
            // Both columns carry the whole day: the one who is in, and the one
            // still expected — who reads as "Not Inside" rather than vanishing.
            ->assertJsonCount(2, 'data.visitors')
            ->assertJsonPath('data.visitors.0.is_inside', true)
            ->assertJsonPath('data.visitors.1.is_verified', false)
            ->assertJsonPath('data.visitors.1.is_inside', false)
            ->assertJsonCount(2, 'data.pass_requests');
    }

    public function test_an_exited_visitor_reads_differently_from_one_still_pending(): void
    {
        // Checked out — must not read the same as "never arrived".
        $exited = $this->pass(['code' => '333333', 'visitor_name' => 'Already Left']);
        $exited->forceFill(['status' => VisitorPass::VERIFIED, 'verified_at' => now()->subHour()])->save();
        $exited->markExited();

        // Still expected — genuinely "Not Inside".
        $this->pass(['code' => '444444', 'visitor_name' => 'Not Yet Here']);

        $data = $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.visitors')
            ->json('data.visitors');

        $left = collect($data)->firstWhere('name', 'Already Left');
        $this->assertFalse($left['is_inside']);
        $this->assertTrue($left['is_exited']);

        $notHere = collect($data)->firstWhere('name', 'Not Yet Here');
        $this->assertFalse($notHere['is_inside']);
        $this->assertFalse($notHere['is_exited']);
    }

    public function test_the_requests_column_keeps_showing_visitors_after_they_are_verified(): void
    {
        // Figma 257:1336 shows verified entries alongside pending ones — working
        // through the queue must not blank the column.
        $verified = $this->pass(['code' => '777777', 'visitor_name' => 'Already In']);
        $verified->forceFill(['status' => VisitorPass::VERIFIED, 'verified_at' => now()])->save();
        $this->pass(['code' => '888888', 'visitor_name' => 'Still Waiting']);

        $data = $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.pass_requests')
            // Still-to-do first, so the officer's next job is at the top.
            ->assertJsonPath('data.pass_requests.0.name', 'Still Waiting')
            ->assertJsonPath('data.pass_requests.0.is_verified', false)
            ->assertJsonPath('data.pass_requests.1.name', 'Already In')
            ->assertJsonPath('data.pass_requests.1.is_verified', true)
            ->json('data');

        // The card renders a case reference beside the code (Figma 257:1358).
        $this->assertMatchesRegularExpression('/^VP-\d{4}-\d+$/', $data['pass_requests'][0]['reference']);
    }

    public function test_visitors_today_counts_who_arrived_today_not_who_was_invited_today(): void
    {
        // Invited days ago, walked in this morning — belongs on today's list.
        $arrivedToday = $this->pass(['code' => '444444', 'visitor_name' => 'Arrived Today']);
        $arrivedToday->forceFill([
            'created_at' => now()->subDays(3),
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now()->subHour(),
        ])->save();

        // Invited today but verified last week — that was last week's visit.
        $arrivedLastWeek = $this->pass(['code' => '555555', 'visitor_name' => 'Arrived Last Week']);
        $arrivedLastWeek->forceFill([
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now()->subDays(7),
        ])->save();

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonCount(1, 'data.visitors')
            ->assertJsonPath('data.visitors.0.name', 'Arrived Today')
            ->assertJsonPath('data.stats.verified_passes', 1);
    }

    public function test_a_pass_issued_late_last_night_is_still_in_the_officers_queue(): void
    {
        // The visitor is walking up to the gate at 00:05 — the request column is
        // the officer's work queue, not a calendar-day report.
        $this->travelTo(today()->addHours(2)); // 02:00 this morning
        $pass = $this->pass(['code' => '333333', 'visitor_name' => 'Late Visitor']);
        $pass->forceFill(['created_at' => today()->subMinutes(30)])->save(); // 23:30 last night

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonCount(1, 'data.pass_requests')
            ->assertJsonPath('data.pass_requests.0.name', 'Late Visitor');
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

    public function test_officer_can_check_out_a_visitor_who_is_inside(): void
    {
        $pass = $this->pass(['status' => VisitorPass::VERIFIED, 'verified_at' => now()->subHour()]);

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/visitors/{$pass->id}/exit")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.is_inside', false);

        $this->assertNotNull($pass->fresh()->exited_at);
    }

    public function test_checking_out_a_visitor_who_never_arrived_is_rejected(): void
    {
        $pass = $this->pass(); // pending — never granted

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/visitors/{$pass->id}/exit")
            ->assertStatus(409);

        $this->assertNull($pass->fresh()->exited_at);
    }

    public function test_checking_out_an_already_exited_visitor_is_a_no_op(): void
    {
        $pass = $this->pass(['status' => VisitorPass::VERIFIED, 'verified_at' => now()->subHours(2)]);
        $pass->markExited();
        $firstExit = $pass->fresh()->exited_at;

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/visitors/{$pass->id}/exit")
            ->assertOk()
            ->assertJsonPath('data.is_inside', false);

        $this->assertTrue($pass->fresh()->exited_at->equalTo($firstExit));
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
