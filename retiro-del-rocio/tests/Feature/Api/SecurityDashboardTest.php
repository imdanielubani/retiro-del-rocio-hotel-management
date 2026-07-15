<?php

namespace Tests\Feature\Api;

use App\Events\SosAlertChanged;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The security tablet's hotel-wide dashboard and its incident actions.
 */
class SecurityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-sec',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);
    }

    /** A signed-in security officer, with a real JWT bearer token. */
    private function officerToken(): string
    {
        Role::findOrCreate('security');

        $user = User::factory()->create(['status' => 'active', 'name' => 'Daniel Ubani']);
        $user->assignRole('security');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function activeAlert(): SosAlert
    {
        return SosAlert::create([
            'room_unit_id' => $this->unit->id,
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'guest_name' => 'James Anderson',
            'status' => SosAlert::ACTIVE,
            'raised_at' => now(),
        ]);
    }

    public function test_the_dashboard_lists_open_incidents_and_counts(): void
    {
        $this->activeAlert();

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/overview')
            ->assertOk()
            ->assertJsonPath('data.officer.name', 'Daniel Ubani')
            ->assertJsonPath('data.stats.active_incidents', 1)
            ->assertJsonPath('data.incidents.0.room_number', '101')
            ->assertJsonPath('data.incidents.0.guest_name', 'James Anderson');
    }

    public function test_responding_acknowledges_the_incident_and_notifies(): void
    {
        Event::fake([SosAlertChanged::class]);
        $alert = $this->activeAlert();

        $this->withToken($this->officerToken())
            ->postJson("/api/v1/security/incidents/{$alert->id}/respond")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->assertDatabaseHas('sos_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
        ]);

        // The guest tablet flips to "Security is on their way" off this broadcast.
        Event::assertDispatched(SosAlertChanged::class);
    }

    public function test_resolving_closes_the_incident(): void
    {
        $alert = $this->activeAlert();
        $token = $this->officerToken();

        $this->withToken($token)
            ->postJson("/api/v1/security/incidents/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        // It drops off the open-incidents list.
        $this->withToken($token)
            ->getJson('/api/v1/security/overview')
            ->assertJsonPath('data.stats.active_incidents', 0)
            ->assertJsonCount(0, 'data.incidents');
    }

    public function test_the_logs_list_every_status_with_a_case_number(): void
    {
        $this->activeAlert();
        SosAlert::create([
            'room_unit_id' => $this->unit->id,
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'status' => SosAlert::RESOLVED,
            'raised_at' => now()->subDay(),
            'resolved_at' => now()->subDay()->addMinutes(5),
        ]);

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/incidents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Newest first, and every record carries a "SOS-…"" case reference.
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.1.status', 'resolved')
            ->assertJsonFragment(['case_no' => 'SOS-'.now()->format('ym').'-'.str_pad((string) SosAlert::where('status', 'active')->first()->id, 3, '0', STR_PAD_LEFT)]);
    }

    public function test_the_logs_can_be_filtered_by_status(): void
    {
        $this->activeAlert();
        SosAlert::create([
            'room_unit_id' => $this->unit->id,
            'room_number' => '101',
            'status' => SosAlert::RESOLVED,
            'raised_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        $this->withToken($this->officerToken())
            ->getJson('/api/v1/security/incidents?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'resolved');
    }

    public function test_a_non_security_user_is_forbidden(): void
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/security/overview')
            ->assertForbidden();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/security/overview')->assertUnauthorized();
    }
}
