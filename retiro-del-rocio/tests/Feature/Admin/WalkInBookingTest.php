<?php

namespace Tests\Feature\Admin;

use App\Events\BookingConfirmed;
use App\Livewire\Admin\Bookings\Index;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Bookings: creating a walk-in booking from the dashboard and the
 * "Walk-in" indicator shown across the list.
 */
class WalkInBookingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function room(): Room
    {
        return Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);
    }

    public function test_admin_can_create_a_walk_in_booking(): void
    {
        Mail::fake();
        // The confirmed-booking event triggers real TTLock provisioning; that is
        // out of scope here (and guarded in production), so record it and move on.
        Event::fake([BookingConfirmed::class]);
        $room = $this->room();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('openCreate')
            ->set('cName', 'Walk In Wanda')
            ->set('cRoomId', $room->id)
            ->set('cCheckIn', today()->toDateString())
            ->set('cCheckOut', today()->addDays(2)->toDateString())
            ->set('cGuests', 2)
            ->set('cStatus', 'paid')
            ->set('cSource', 'walk_in')
            ->call('createBooking')
            ->assertHasNoErrors();

        $booking = Booking::where('customer_name', 'Walk In Wanda')->first();
        $this->assertNotNull($booking);
        $this->assertSame(Booking::SOURCE_WALK_IN, $booking->source);
        $this->assertTrue($booking->isWalkIn());
        $this->assertSame('Walk-in', $booking->originLabel());
        // Marked paid at the desk, so reception can check them straight in.
        $this->assertSame('paid', $booking->status);
    }

    public function test_the_source_must_be_a_known_channel(): void
    {
        $room = $this->room();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('openCreate')
            ->set('cName', 'Bad Source')
            ->set('cRoomId', $room->id)
            ->set('cCheckIn', today()->toDateString())
            ->set('cCheckOut', today()->addDays(1)->toDateString())
            ->set('cSource', 'carrier-pigeon')
            ->call('createBooking')
            ->assertHasErrors(['cSource']);
    }

    public function test_the_list_flags_walk_ins_but_not_online_bookings(): void
    {
        $room = $this->room();
        Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Walk In Wanda',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'paid',
            'source' => Booking::SOURCE_WALK_IN,
        ]);
        Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Online Olivia',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'paid',
            // source defaults to 'online'
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Walk In Wanda')
            ->assertSee('Online Olivia')
            ->assertSee('Walk-in');
    }
}
