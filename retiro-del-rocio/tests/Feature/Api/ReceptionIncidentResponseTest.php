<?php

namespace Tests\Feature\Api;

use App\Models\SosAlert;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reception's SOS incident response. SOS alerts are hotel-wide, so the front
 * desk sees the same emergencies security does and can acknowledge / resolve
 * them — the desk is often the closest staffed station to a guest in trouble.
 */
class ReceptionIncidentResponseTest extends TestCase
{
    use RefreshDatabase;

    private function receptionUser(): User
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Daniel Ubani']);
        $user->assignRole('reception');

        return $user;
    }

    private function tokenFor(User $user): string
    {
        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');

        return $this->tokenFor($user);
    }

    private function alert(string $status, array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'room_number' => '104',
            'suite_name' => 'Brisa Residence',
            'guest_name' => 'Ada Lovelace',
            'status' => $status,
            'raised_at' => now()->subMinutes(5),
        ], $overrides));
    }

    public function test_the_overview_ships_open_incidents_in_full_shape(): void
    {
        $this->alert(SosAlert::ACTIVE, ['room_number' => '104', 'raised_at' => now()->subMinute()]);
        $this->alert(SosAlert::ACKNOWLEDGED, ['room_number' => '105', 'raised_at' => now()->subMinutes(3)]);
        // A resolved one is closed, so it is not "open" and stays off both lists.
        $this->alert(SosAlert::RESOLVED, ['room_number' => '106']);

        $this->withToken($this->tokenFor($this->receptionUser()))
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.alerts')
            ->assertJsonCount(2, 'data.incidents')
            ->assertJsonPath('data.incidents.0.room_number', '104')
            ->assertJsonPath('data.incidents.0.status', SosAlert::ACTIVE)
            ->assertJsonStructure(['data' => ['incidents' => [['id', 'case_no', 'status', 'room_number', 'guest_name', 'raised_at']]]]);
    }

    public function test_reception_lists_every_incident_newest_first(): void
    {
        $this->alert(SosAlert::RESOLVED, ['room_number' => '101', 'raised_at' => now()->subMinutes(30)]);
        $this->alert(SosAlert::ACTIVE, ['room_number' => '102', 'raised_at' => now()->subMinute()]);

        $this->withToken($this->tokenFor($this->receptionUser()))
            ->getJson('/api/v1/reception/incidents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Newest raised first.
            ->assertJsonPath('data.0.room_number', '102')
            ->assertJsonPath('data.1.room_number', '101')
            ->assertJsonStructure(['data' => [['id', 'case_no', 'status', 'raised_at', 'resolved_at']]]);
    }

    public function test_reception_can_filter_incidents_by_status(): void
    {
        $this->alert(SosAlert::ACTIVE, ['room_number' => '201']);
        $this->alert(SosAlert::RESOLVED, ['room_number' => '202']);

        $this->withToken($this->tokenFor($this->receptionUser()))
            ->getJson('/api/v1/reception/incidents?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.room_number', '201');
    }

    public function test_reception_can_acknowledge_an_incident(): void
    {
        $user = $this->receptionUser();
        $alert = $this->alert(SosAlert::ACTIVE);

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/reception/incidents/{$alert->id}/respond")
            ->assertOk()
            ->assertJsonPath('data.status', SosAlert::ACKNOWLEDGED)
            ->assertJsonPath('data.acknowledged_by', 'Daniel Ubani');

        $fresh = $alert->fresh();
        $this->assertSame(SosAlert::ACKNOWLEDGED, $fresh->status);
        $this->assertSame($user->id, $fresh->acknowledged_by);
        $this->assertNotNull($fresh->acknowledged_at);
    }

    public function test_reception_can_resolve_an_incident(): void
    {
        $user = $this->receptionUser();
        $alert = $this->alert(SosAlert::ACKNOWLEDGED, ['acknowledged_at' => now()->subMinute()]);

        $this->withToken($this->tokenFor($user))
            ->postJson("/api/v1/reception/incidents/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', SosAlert::RESOLVED);

        $fresh = $alert->fresh();
        $this->assertSame(SosAlert::RESOLVED, $fresh->status);
        $this->assertSame($user->id, $fresh->resolved_by);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_acknowledging_a_closed_incident_is_a_no_op(): void
    {
        $alert = $this->alert(SosAlert::RESOLVED, ['resolved_at' => now()->subMinute()]);

        $this->withToken($this->tokenFor($this->receptionUser()))
            ->postJson("/api/v1/reception/incidents/{$alert->id}/respond")
            ->assertOk()
            // Still resolved — a stale tap cannot reopen a closed incident.
            ->assertJsonPath('data.status', SosAlert::RESOLVED);

        $this->assertSame(SosAlert::RESOLVED, $alert->fresh()->status);
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $alert = $this->alert(SosAlert::ACTIVE);
        $token = $this->otherRoleToken();

        $this->withToken($token)->getJson('/api/v1/reception/incidents')->assertForbidden();
        $this->withToken($token)
            ->postJson("/api/v1/reception/incidents/{$alert->id}/respond")
            ->assertForbidden();
    }
}
