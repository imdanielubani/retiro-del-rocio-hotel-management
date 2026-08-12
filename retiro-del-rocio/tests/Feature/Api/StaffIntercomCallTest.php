<?php

namespace Tests\Feature\Api;

use App\Events\IntercomCallRinging;
use App\Events\IntercomCallUpdated;
use App\Models\IntercomCall;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff Intercom — calls placed to one specific staff member, not a whole
 * department. Two accounts holding the same role are rung individually.
 */
class StaffIntercomCallTest extends TestCase
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

    public function test_housekeeping_can_call_maintenance(): void
    {
        Event::fake([IntercomCallRinging::class]);

        [$housekeeper, $housekeepingToken] = $this->userFor('housekeeping', 'Ada');
        [$maintainer] = $this->userFor('maintenance', 'Musa');

        $data = $this->withToken($housekeepingToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $maintainer->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.from.role', 'housekeeping')
            ->assertJsonPath('data.from.user_id', $housekeeper->id)
            ->assertJsonPath('data.from.label', 'Ada')
            ->assertJsonPath('data.to.role', 'maintenance')
            ->assertJsonPath('data.to.user_id', $maintainer->id)
            ->assertJsonPath('data.to.label', 'Musa')
            ->json('data');

        $this->assertDatabaseHas('intercom_calls', [
            'id' => $data['id'],
            'from_user_id' => $housekeeper->id,
            'from_role' => 'housekeeping',
            'to_user_id' => $maintainer->id,
            'to_role' => 'maintenance',
            'status' => 'ringing',
        ]);

        Event::assertDispatched(
            IntercomCallRinging::class,
            fn (IntercomCallRinging $e) => $e->toRoomUnitId === null && $e->toUserId === $maintainer->id,
        );
    }

    public function test_two_accounts_holding_the_same_role_are_rung_individually(): void
    {
        [$bar1, $bar1Token] = $this->userFor('bar', 'Bar 1');
        [$bar2] = $this->userFor('bar', 'Bar 2');
        [, $kitchenToken] = $this->userFor('kitchen');

        $data = $this->withToken($kitchenToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $bar1->id])
            ->assertCreated()
            ->json('data');

        // Bar 1 sees it as their current call; Bar 2 does not.
        $this->withToken($bar1Token)
            ->getJson('/api/v1/staff/intercom/calls/current')
            ->assertOk()
            ->assertJsonPath('data.id', $data['id']);

        $bar2Token = app(JwtService::class)->issue(['sub' => $bar2->id])['token'];
        $this->withToken($bar2Token)
            ->getJson('/api/v1/staff/intercom/calls/current')
            ->assertOk()
            ->assertJson(['data' => null]);

        // And only Bar 1 — not Bar 2 — can answer it.
        $this->withToken($bar2Token)
            ->postJson("/api/v1/staff/intercom/calls/{$data['id']}/answer")
            ->assertStatus(403);

        $this->withToken($bar1Token)
            ->postJson("/api/v1/staff/intercom/calls/{$data['id']}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_a_staff_member_cannot_call_themselves(): void
    {
        [$officer, $securityToken] = $this->userFor('security');

        $this->withToken($securityToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $officer->id])
            ->assertStatus(404);
    }

    public function test_an_unknown_user_cannot_be_called(): void
    {
        $this->withToken($this->tokenFor('security'))
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => 999999])
            ->assertStatus(404);
    }

    public function test_a_non_staff_user_cannot_be_called(): void
    {
        Role::findOrCreate('valet');
        $valet = User::factory()->create(['status' => 'active']);
        $valet->assignRole('valet');

        $this->withToken($this->tokenFor('security'))
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $valet->id])
            ->assertStatus(404);
    }

    public function test_a_staffer_already_on_a_call_cannot_place_another(): void
    {
        [$housekeeper, $housekeepingToken] = $this->userFor('housekeeping');
        [$maintainer] = $this->userFor('maintenance');
        [$officer] = $this->userFor('security');

        $this->withToken($housekeepingToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $maintainer->id])
            ->assertCreated();

        $this->withToken($housekeepingToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $officer->id])
            ->assertStatus(409);
    }

    public function test_the_callee_can_answer(): void
    {
        [$officer] = $this->userFor('security');
        [$maintainer, $maintenanceToken] = $this->userFor('maintenance');

        $call = IntercomCall::create([
            'from_user_id' => $officer->id, 'from_role' => 'security', 'from_label' => 'Security',
            'to_user_id' => $maintainer->id, 'to_role' => 'maintenance', 'to_label' => 'Maintenance',
        ]);

        $this->withToken($maintenanceToken)
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_the_caller_cannot_answer_their_own_call(): void
    {
        [$officer, $securityToken] = $this->userFor('security');
        [$maintainer] = $this->userFor('maintenance');

        $call = IntercomCall::create([
            'from_user_id' => $officer->id, 'from_role' => 'security', 'from_label' => 'Security',
            'to_user_id' => $maintainer->id, 'to_role' => 'maintenance', 'to_label' => 'Maintenance',
        ]);

        $this->withToken($securityToken)
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/answer")
            ->assertStatus(403);
    }

    public function test_ending_an_accepted_call_broadcasts_to_both_users(): void
    {
        Event::fake([IntercomCallUpdated::class]);

        [$housekeeper] = $this->userFor('housekeeping');
        [$officer, $securityToken] = $this->userFor('security');

        $call = IntercomCall::create([
            'from_user_id' => $housekeeper->id, 'from_role' => 'housekeeping', 'from_label' => 'Housekeeping',
            'to_user_id' => $officer->id, 'to_role' => 'security', 'to_label' => 'Security',
            'status' => IntercomCall::ACCEPTED, 'answered_at' => now(),
        ]);

        $this->withToken($securityToken)
            ->postJson("/api/v1/staff/intercom/calls/{$call->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        Event::assertDispatched(
            IntercomCallUpdated::class,
            fn (IntercomCallUpdated $e) => $e->fromUserId === $housekeeper->id && $e->toUserId === $officer->id,
        );
    }

    public function test_current_returns_null_with_no_active_call(): void
    {
        $this->withToken($this->tokenFor('maintenance'))
            ->getJson('/api/v1/staff/intercom/calls/current')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_token_returns_agora_credentials_and_rejects_a_call_that_has_ended(): void
    {
        [$officer, $securityToken] = $this->userFor('security');
        [$maintainer] = $this->userFor('maintenance');

        $call = IntercomCall::create([
            'from_user_id' => $officer->id, 'from_role' => 'security', 'from_label' => 'Security',
            'to_user_id' => $maintainer->id, 'to_role' => 'maintenance', 'to_label' => 'Maintenance',
            'status' => IntercomCall::ACCEPTED, 'answered_at' => now(),
        ]);

        $this->withToken($securityToken)
            ->getJson("/api/v1/staff/intercom/calls/{$call->id}/token")
            ->assertOk()
            ->assertJsonPath('data.channel', "intercom-{$call->id}")
            ->assertJsonPath('data.uid', 1);

        $call->update(['status' => IntercomCall::ENDED, 'ended_at' => now()]);

        $this->withToken($securityToken)
            ->getJson("/api/v1/staff/intercom/calls/{$call->id}/token")
            ->assertStatus(409);
    }

    /**
     * The full staff mesh — every tablet role can call every other one, as
     * individuals. One assertion per ordered pair covers the whole
     * directory in a single test rather than duplicating the same
     * place→answer round trip per pair.
     */
    public function test_every_role_can_call_every_other_role(): void
    {
        // Twelve ordered pairs × three requests each would otherwise trip
        // the per-minute throttle on these routes — irrelevant to what this
        // test is checking (that every pair CAN call, not how fast).
        $this->withoutMiddleware(ThrottleRequests::class);

        $roles = ['reception', 'housekeeping', 'maintenance', 'security', 'bar', 'kitchen'];

        foreach ($roles as $callerRole) {
            foreach ($roles as $calleeRole) {
                if ($callerRole === $calleeRole) {
                    continue;
                }

                [$caller, $callerToken] = $this->userFor($callerRole, "Caller-{$callerRole}-{$calleeRole}");
                [$callee, $calleeToken] = $this->userFor($calleeRole, "Callee-{$callerRole}-{$calleeRole}");

                $data = $this->withToken($callerToken)
                    ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $callee->id])
                    ->assertCreated()
                    ->assertJsonPath('data.from.role', $callerRole)
                    ->assertJsonPath('data.to.role', $calleeRole)
                    ->json('data');

                $this->withToken($calleeToken)
                    ->postJson("/api/v1/staff/intercom/calls/{$data['id']}/answer")
                    ->assertOk()
                    ->assertJsonPath('data.status', 'accepted');

                $this->withToken($callerToken)
                    ->postJson("/api/v1/staff/intercom/calls/{$data['id']}/end")
                    ->assertOk();
            }
        }
    }

    public function test_a_staff_call_to_a_reception_user_is_visible_via_the_reception_endpoint(): void
    {
        // Reception's own screen has two ways to place a call (guest vs staff
        // tab), but they share the same underlying table — a staff-placed
        // call to a reception user must show up on reception's own "current"
        // call just like a guest-placed one does.
        [$receptionist] = $this->userFor('reception');
        [, $housekeepingToken] = $this->userFor('housekeeping');

        $this->withToken($housekeepingToken)
            ->postJson('/api/v1/staff/intercom/calls', ['user_id' => $receptionist->id])
            ->assertCreated();

        $this->withToken(app(JwtService::class)->issue(['sub' => $receptionist->id])['token'])
            ->getJson('/api/v1/reception/intercom/calls/current')
            ->assertOk()
            ->assertJsonPath('data.from.role', 'housekeeping')
            ->assertJsonPath('data.to.role', 'reception');
    }
}
