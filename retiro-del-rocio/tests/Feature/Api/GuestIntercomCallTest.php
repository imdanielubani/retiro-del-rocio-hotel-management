<?php

namespace Tests\Feature\Api;

use App\Events\IntercomCallRinging;
use App\Events\IntercomCallUpdated;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\IntercomCall;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestIntercomCallTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnit $unit;

    private string $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();

        $room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-intercom',
            'type' => 'suite',
            'price' => 150000,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
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
        $this->unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-guest-intercom']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-GI-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);
        $this->deviceToken = $device->createToken('tablet')->plainTextToken;
    }

    public function test_a_guest_can_call_reception(): void
    {
        Event::fake([IntercomCallRinging::class]);

        $data = $this->withToken($this->deviceToken)
            ->postJson('/api/v1/intercom/calls')
            ->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.from.label', 'Room 101')
            ->assertJsonPath('data.from.sublabel', 'Daniel Ubani')
            ->assertJsonPath('data.to.role', 'reception')
            ->assertJsonPath('data.to.label', 'Reception')
            ->json('data');

        $this->assertDatabaseHas('intercom_calls', [
            'id' => $data['id'],
            'from_room_unit_id' => $this->unit->id,
            'to_role' => 'reception',
            'status' => 'ringing',
        ]);

        Event::assertDispatched(
            IntercomCallRinging::class,
            fn (IntercomCallRinging $e) => $e->toRoomUnitId === null && $e->toRole === 'reception',
        );
    }

    public function test_a_second_call_from_the_same_room_is_rejected_while_one_is_active(): void
    {
        $this->withToken($this->deviceToken)->postJson('/api/v1/intercom/calls')->assertCreated();

        $this->withToken($this->deviceToken)
            ->postJson('/api/v1/intercom/calls')
            ->assertStatus(409);
    }

    public function test_the_guest_can_cancel_a_call_before_it_is_answered(): void
    {
        $callId = $this->withToken($this->deviceToken)
            ->postJson('/api/v1/intercom/calls')
            ->json('data.id');

        $this->withToken($this->deviceToken)
            ->postJson("/api/v1/intercom/calls/{$callId}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_the_guest_can_answer_an_incoming_call_from_reception(): void
    {
        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'from_sublabel' => 'Front desk assistance',
            'to_room_unit_id' => $this->unit->id,
            'to_label' => 'Room 101',
            'to_sublabel' => 'Daniel Ubani',
        ]);

        $this->withToken($this->deviceToken)
            ->postJson("/api/v1/intercom/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertNotNull($call->fresh()->answered_at);
    }

    public function test_the_guest_can_decline_an_incoming_call_from_reception(): void
    {
        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'to_room_unit_id' => $this->unit->id,
            'to_label' => 'Room 101',
        ]);

        $this->withToken($this->deviceToken)
            ->postJson("/api/v1/intercom/calls/{$call->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
    }

    public function test_a_call_for_another_room_cannot_be_answered_by_this_device(): void
    {
        $otherUnit = RoomUnit::create([
            'room_id' => $this->unit->room_id,
            'number' => '202',
            'status' => 'occupied',
        ]);
        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'to_room_unit_id' => $otherUnit->id,
            'to_label' => 'Room 202',
        ]);

        $this->withToken($this->deviceToken)
            ->postJson("/api/v1/intercom/calls/{$call->id}/answer")
            ->assertStatus(403);
    }

    public function test_ending_an_accepted_call_broadcasts_to_both_parties(): void
    {
        Event::fake([IntercomCallUpdated::class]);

        $call = IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'to_role' => 'reception',
            'to_label' => 'Reception',
            'status' => IntercomCall::ACCEPTED,
            'answered_at' => now(),
        ]);

        $this->withToken($this->deviceToken)
            ->postJson("/api/v1/intercom/calls/{$call->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        Event::assertDispatched(
            IntercomCallUpdated::class,
            fn (IntercomCallUpdated $e) => $e->fromRoomUnitId === $this->unit->id && $e->toRole === 'reception',
        );
    }

    public function test_current_returns_null_when_the_room_has_no_active_call(): void
    {
        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/intercom/calls/current')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_current_returns_the_rooms_active_call_when_reception_is_the_caller(): void
    {
        $call = IntercomCall::create([
            'from_role' => 'reception',
            'from_label' => 'Reception',
            'to_room_unit_id' => $this->unit->id,
            'to_label' => 'Room 101',
        ]);

        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/intercom/calls/current')
            ->assertOk()
            ->assertJsonPath('data.id', $call->id);
    }

    public function test_current_still_shows_a_call_the_other_side_just_ended(): void
    {
        // Reception ends the call, not the guest — the guest's tablet must
        // still see it briefly to render its own "Call Ended" screen,
        // instead of the call just vanishing on their side.
        $call = IntercomCall::create([
            'from_room_unit_id' => $this->unit->id,
            'from_label' => 'Room 101',
            'to_role' => 'reception',
            'to_label' => 'Reception',
            'status' => IntercomCall::ACCEPTED,
            'answered_at' => now(),
        ]);
        $call->update(['status' => IntercomCall::ENDED, 'ended_at' => now()]);

        $this->withToken($this->deviceToken)
            ->getJson('/api/v1/intercom/calls/current')
            ->assertOk()
            ->assertJsonPath('data.id', $call->id)
            ->assertJsonPath('data.status', 'ended');
    }

    public function test_an_unauthenticated_request_cannot_place_a_call(): void
    {
        $this->postJson('/api/v1/intercom/calls')->assertStatus(401);
    }
}
