<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Housekeeping\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Housekeeping → Room Status — a desk view of every room's current
 * cleanliness, mirroring the housekeeping tablet's own Dashboard/Rooms
 * screens.
 */
class HousekeepingRoomStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function unit(array $overrides = []): RoomUnit
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(array_merge([
            'room_id' => $room->id,
            'number' => (string) random_int(100, 999),
            'status' => 'available',
        ], $overrides));
    }

    public function test_it_lists_every_room_with_its_housekeeping_status(): void
    {
        $this->unit(['number' => '101', 'housekeeping_status' => 'dirty']);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->assertOk()
            ->assertSee('Room 101')
            ->assertSee('Dirty');
    }

    public function test_it_shows_the_current_guest_when_occupied(): void
    {
        $unit = $this->unit(['number' => '101', 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'James Anderson',
            'room_id' => $unit->room_id,
            'room_name' => 'Alba Suite',
            'room_unit_id' => $unit->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 450000,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->assertSee('James Anderson');
    }

    public function test_it_filters_by_housekeeping_status(): void
    {
        $this->unit(['number' => '101', 'housekeeping_status' => 'dirty']);
        $this->unit(['number' => '202', 'housekeeping_status' => 'clean']);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->set('statusFilter', 'dirty')
            ->assertSee('Room 101')
            ->assertDontSee('Room 202');
    }

    public function test_it_searches_by_room_number(): void
    {
        $this->unit(['number' => '101']);
        $this->unit(['number' => '202']);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->set('search', '202')
            ->assertSee('Room 202')
            ->assertDontSee('Room 101');
    }

    public function test_a_rooms_housekeeping_status_can_be_changed_from_the_desk(): void
    {
        $unit = $this->unit(['number' => '101', 'housekeeping_status' => 'dirty']);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->call('setRoomStatus', $unit->id, 'clean')
            ->assertDispatched('toast');

        $this->assertSame('clean', $unit->fresh()->housekeeping_status);
        $this->assertNotNull($unit->fresh()->housekeeping_status_at);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $unit = $this->unit(['number' => '101', 'housekeeping_status' => 'dirty']);

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->call('setRoomStatus', $unit->id, 'not-a-real-status')
            ->assertHasErrors(['status']);

        $this->assertSame('dirty', $unit->fresh()->housekeeping_status);
    }

    public function test_it_paginates_beyond_the_first_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->unit(['number' => (string) (300 + $i)]);
        }

        Livewire::actingAs($this->admin())
            ->test(RoomStatus::class)
            ->assertSee('Showing 1–8 of 20 rooms');
    }
}
