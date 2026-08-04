<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Housekeeping\Requests;
use App\Models\Booking;
use App\Models\HousekeepingRequest;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Housekeeping → Service Requests — the combined housekeeping +
 * maintenance log of every guest ask, the admin mirror of the guest tablet's
 * own history screen.
 */
class HousekeepingRequestsLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function booking(string $roomNumber = '101', string $guest = 'Daniel Ubani'): Booking
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => $roomNumber, 'status' => 'occupied']);

        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => $guest,
            'room_id' => $room->id,
            'room_name' => $room->name,
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

        return $booking->fresh();
    }

    public function test_it_lists_housekeeping_and_maintenance_requests_together(): void
    {
        $booking = $this->booking('101', 'James Anderson');

        HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);

        WorkOrder::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'title' => 'AC not cooling',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertOk()
            ->assertSee('James Anderson')
            ->assertSee('Towels')
            ->assertSee('AC not cooling');
    }

    public function test_checkout_inspection_never_appears_in_the_log(): void
    {
        $booking = $this->booking();

        HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => HousekeepingRequest::CHECKOUT_INSPECTION,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->set('range', '')
            ->assertDontSee('Checkout Inspection');
    }

    public function test_a_staff_reported_fault_with_no_booking_is_excluded(): void
    {
        $booking = $this->booking();

        WorkOrder::create([
            'room_unit_id' => $booking->room_unit_id,
            'title' => 'Staff-reported fault',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->set('range', '')
            ->assertDontSee('Staff-reported fault');
    }

    public function test_it_filters_by_category(): void
    {
        $booking = $this->booking();

        HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);

        WorkOrder::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'title' => 'Broken lamp',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->set('category', 'housekeeping')
            ->assertSee('Towels')
            ->assertDontSee('Broken lamp');
    }

    public function test_it_filters_by_open_status(): void
    {
        $booking = $this->booking();

        $open = HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);

        $completed = HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'amenities',
        ]);
        $completed->complete();

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->set('status', 'open')
            ->assertSee('Towels')
            ->assertDontSee('Amenities');
    }

    public function test_it_searches_by_room_number(): void
    {
        $booking101 = $this->booking('101', 'James Anderson');
        $booking202 = $this->booking('202', 'Grace Hopper');

        HousekeepingRequest::create(['room_unit_id' => $booking101->room_unit_id, 'booking_id' => $booking101->id, 'type' => 'towels']);
        HousekeepingRequest::create(['room_unit_id' => $booking202->room_unit_id, 'booking_id' => $booking202->id, 'type' => 'amenities']);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->set('search', '202')
            ->assertSee('Grace Hopper')
            ->assertDontSee('James Anderson');
    }

    public function test_it_defaults_to_the_current_month(): void
    {
        $booking = $this->booking();

        $oldRequest = HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);
        $oldRequest->forceFill(['created_at' => now()->subMonths(2)])->save();

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertDontSee('Towels');
    }

    public function test_the_room_column_shows_the_suite_name_and_category(): void
    {
        $booking = $this->booking('101', 'James Anderson');

        HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertSee('Room 101')
            ->assertSee('Alba Suite')
            ->assertSee('suite');
    }

    public function test_the_completed_by_column_shows_who_completed_a_housekeeping_request(): void
    {
        $booking = $this->booking();
        $officer = User::factory()->create(['name' => 'Grace Hopper', 'status' => 'active']);

        $request = HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);
        $request->complete($officer);

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertSee('Grace Hopper');
    }

    public function test_the_completed_by_column_shows_the_technician_for_a_done_work_order(): void
    {
        $booking = $this->booking();
        $technician = User::factory()->create(['name' => 'James Anderson', 'status' => 'active']);

        $order = WorkOrder::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'title' => 'AC not cooling',
        ]);
        $order->accept($technician);
        $order->start();
        $order->complete();

        Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertSee('James Anderson');
    }

    public function test_it_paginates_beyond_the_first_page(): void
    {
        $booking = $this->booking();

        for ($i = 0; $i < 20; $i++) {
            $request = HousekeepingRequest::create([
                'room_unit_id' => $booking->room_unit_id,
                'booking_id' => $booking->id,
                'type' => 'towels',
                'notes' => 'Request #'.$i,
            ]);
            $request->forceFill(['created_at' => now()->subMinutes(20 - $i)])->save();
        }

        $component = Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->assertSee('Showing 1–8 of 20 requests')
            ->assertSee('Request #19');

        $component->assertDontSee('Request #0');

        $component->call('nextPage')
            ->assertSee('Showing 9–16 of 20 requests')
            ->assertSee('Request #11');

        $component->call('nextPage')
            ->assertSee('Showing 17–20 of 20 requests')
            ->assertSee('Request #0');
    }

    public function test_it_exports_a_csv(): void
    {
        $booking = $this->booking();

        HousekeepingRequest::create([
            'room_unit_id' => $booking->room_unit_id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);

        $response = Livewire::actingAs($this->admin())
            ->test(Requests::class)
            ->call('export');

        $response->assertFileDownloaded();
    }
}
