<?php

namespace Tests\Feature\Api;

use App\Events\IntercomCallRinging;
use App\Events\IntercomCallUpdated;
use App\Models\Booking;
use App\Models\IntercomCall;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptionIntercomCallTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-reception-intercom',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
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

    public function test_reception_can_call_a_checked_in_guests_room(): void
    {
        Event::fake([IntercomCallRinging::class]);

        $data = $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/intercom/calls', ['booking_id' => $this->booking->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.from.role', 'reception')
            ->assertJsonPath('data.to.label', 'Room 101')
            ->assertJsonPath('data.to.sublabel', 'Daniel Ubani')
            ->json('data');

        $this->assertDatabaseHas('intercom_calls', [
            'id' => $data['id'],
            'from_role' => 'reception',
            'to_room_unit_id' => $this->unit->id,
            'status' => 'ringing',
        ]);

        Event::assertDispatched(
            IntercomCallRinging::class,
            fn (IntercomCallRinging $e) => $e->toRoomUnitId === $this->unit->id,
        );
    }

    public function test_reception_cannot_call_a_guest_who_is_not_checked_in(): void
    {
        $this->booking->update(['status' => 'checked_out']);

        $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/intercom/calls', ['booking_id' => $this->booking->id])
            ->assertStatus(404);
    }

    public function test_a_non_reception_role_cannot_place_an_intercom_call(): void
    {
        $this->withToken($this->otherRoleToken())
            ->postJson('/api/v1/reception/intercom/calls', ['booking_id' => $this->booking->id])
            ->assertStatus(403);
    }

    public function test_reception_can_answer_an_incoming_call_from_a_guest(): void
    {
        $call = IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'from_sublabel' => 'Daniel Ubani',
            'to_role' => 'reception',
            'to_label' => 'Reception',
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/intercom/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertNotNull($call->fresh()->answered_at);
    }

    public function test_reception_can_decline_an_incoming_call_from_a_guest(): void
    {
        $call = IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'to_role' => 'reception',
            'to_label' => 'Reception',
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/intercom/calls/{$call->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_ending_an_accepted_call_broadcasts_to_both_parties(): void
    {
        Event::fake([IntercomCallUpdated::class]);

        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'to_room_unit_id' => $this->unit->id,
            'to_label' => 'Room 101',
            'status' => IntercomCall::ACCEPTED,
            'answered_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/intercom/calls/{$call->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        Event::assertDispatched(
            IntercomCallUpdated::class,
            fn (IntercomCallUpdated $e) => $e->fromRole === 'reception' && $e->toRoomUnitId === $this->unit->id,
        );
    }

    public function test_reception_cannot_call_a_room_that_already_has_an_active_call(): void
    {
        IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'to_role' => 'reception',
            'to_label' => 'Reception',
        ]);

        $this->withToken($this->receptionToken())
            ->postJson('/api/v1/reception/intercom/calls', ['booking_id' => $this->booking->id])
            ->assertStatus(409);
    }

    public function test_token_returns_agora_credentials_for_reception(): void
    {
        $call = IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'to_role' => 'reception',
            'to_label' => 'Reception',
            'status' => IntercomCall::ACCEPTED,
            'answered_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/intercom/calls/{$call->id}/token")
            ->assertOk()
            ->assertJsonPath('data.channel', "intercom-{$call->id}")
            ->assertJsonPath('data.uid', 2);
    }

    public function test_current_returns_null_when_the_desk_has_no_active_call(): void
    {
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/intercom/calls/current')
            ->assertOk()
            ->assertJson(['data' => null]);
    }
}
