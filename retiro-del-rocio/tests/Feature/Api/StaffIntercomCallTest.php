<?php

namespace Tests\Feature\Api;

use App\Events\IntercomCallRinging;
use App\Events\IntercomCallUpdated;
use App\Models\IntercomCall;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffIntercomCallTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role, string $name = 'Staffer'): string
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole($role);

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    public function test_housekeeping_can_call_maintenance(): void
    {
        Event::fake([IntercomCallRinging::class]);

        $data = $this->withToken($this->tokenFor('housekeeping'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'maintenance'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.from.role', 'housekeeping')
            ->assertJsonPath('data.from.label', 'Housekeeping')
            ->assertJsonPath('data.to.role', 'maintenance')
            ->assertJsonPath('data.to.label', 'Maintenance')
            ->json('data');

        $this->assertDatabaseHas('intercom_calls', [
            'id' => $data['id'],
            'from_role' => 'housekeeping',
            'to_role' => 'maintenance',
            'status' => 'ringing',
        ]);

        Event::assertDispatched(
            IntercomCallRinging::class,
            fn (IntercomCallRinging $e) => $e->toRoomUnitId === null && $e->toRole === 'maintenance',
        );
    }

    public function test_a_station_cannot_call_itself(): void
    {
        $this->withToken($this->tokenFor('security'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'security'])
            ->assertStatus(404);
    }

    public function test_an_unknown_role_cannot_be_called(): void
    {
        $this->withToken($this->tokenFor('security'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'admin'])
            ->assertStatus(404);
    }

    public function test_a_station_already_on_a_call_cannot_place_another(): void
    {
        $this->withToken($this->tokenFor('housekeeping'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'maintenance'])
            ->assertCreated();

        $this->withToken($this->tokenFor('housekeeping'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'security'])
            ->assertStatus(409);
    }

    public function test_the_callee_can_answer(): void
    {
        $call = IntercomCall::create([
            'from_role' => 'security',
            'from_label' => 'Security',
            'to_role' => 'maintenance',
            'to_label' => 'Maintenance',
        ]);

        $this->withToken($this->tokenFor('maintenance'))
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_the_caller_cannot_answer_their_own_call(): void
    {
        $call = IntercomCall::create([
            'from_role' => 'security',
            'from_label' => 'Security',
            'to_role' => 'maintenance',
            'to_label' => 'Maintenance',
        ]);

        $this->withToken($this->tokenFor('security'))
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/answer")
            ->assertStatus(403);
    }

    public function test_ending_an_accepted_call_broadcasts_to_both_stations(): void
    {
        Event::fake([IntercomCallUpdated::class]);

        $call = IntercomCall::create([
            'from_role' => 'housekeeping',
            'from_label' => 'Housekeeping',
            'to_role' => 'security',
            'to_label' => 'Security',
            'status' => IntercomCall::ACCEPTED,
            'answered_at' => now(),
        ]);

        $this->withToken($this->tokenFor('security'))
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        Event::assertDispatched(
            IntercomCallUpdated::class,
            fn (IntercomCallUpdated $e) => $e->fromRole === 'housekeeping' && $e->toRole === 'security',
        );
    }

    public function test_current_returns_null_with_no_active_call(): void
    {
        $this->withToken($this->tokenFor('maintenance'))
            ->getJson('/api/v1/staff/intercom/calls/current')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_reception_placing_a_staff_call_is_visible_via_the_reception_endpoint(): void
    {
        // Reception's own screen has two ways to place a call (guest vs staff
        // tab), but they share the same underlying table — a staff-placed
        // call to reception must show up on reception's own "current" call
        // just like a guest-placed one does.
        $this->withToken($this->tokenFor('housekeeping'))
            ->postJson('/api/v1/staff/intercom/calls', ['role' => 'reception'])
            ->assertCreated();

        $this->withToken($this->tokenFor('reception'))
            ->getJson('/api/v1/reception/intercom/calls/current')
            ->assertOk()
            ->assertJsonPath('data.from.role', 'housekeeping')
            ->assertJsonPath('data.to.role', 'reception');
    }
}
