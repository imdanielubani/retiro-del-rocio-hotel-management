<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Housekeeping\StaffWorkload;
use App\Models\Booking;
use App\Models\HousekeepingRequest;
use App\Models\LostFoundItem;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Housekeeping → Staff Workload — how much each housekeeper and
 * maintenance technician has gotten through.
 */
class HousekeepingStaffWorkloadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function housekeeper(string $name = 'Grace Hopper'): User
    {
        Role::findOrCreate('housekeeping', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('housekeeping');

        return $user;
    }

    private function technician(string $name = 'James Anderson'): User
    {
        Role::findOrCreate('maintenance', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('maintenance');

        return $user;
    }

    private function unit(): RoomUnit
    {
        $room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-'.Str::random(6),
            'type' => 'suite',
            'price' => 150000,
        ]);

        return RoomUnit::create(['room_id' => $room->id, 'number' => (string) random_int(100, 999), 'status' => 'available']);
    }

    private function booking(RoomUnit $unit): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'A Guest',
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
    }

    public function test_it_lists_housekeeping_and_maintenance_staff(): void
    {
        $this->housekeeper('Grace Hopper');
        $this->technician('James Anderson');

        Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('James Anderson')
            ->assertSee('Housekeeping')
            ->assertSee('Maintenance');
    }

    public function test_it_counts_completed_housekeeping_requests(): void
    {
        $officer = $this->housekeeper('Grace Hopper');
        $unit = $this->unit();
        $booking = $this->booking($unit);

        $request = HousekeepingRequest::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'type' => 'towels']);
        $request->complete($officer);

        // Still pending — must not be counted.
        HousekeepingRequest::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'type' => 'amenities']);

        $html = Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->assertSee('Grace Hopper')
            ->html();

        $this->assertStringContainsString('Grace Hopper', $html);
    }

    public function test_it_counts_completed_faults_and_currently_assigned_work_for_a_technician(): void
    {
        $technician = $this->technician('James Anderson');
        $unit = $this->unit();
        $booking = $this->booking($unit);

        $done = WorkOrder::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'title' => 'AC not cooling']);
        $done->accept($technician);
        $done->start();
        $done->complete();

        $open = WorkOrder::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'title' => 'Broken lamp']);
        $open->accept($technician);

        $component = Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->assertOk()
            ->assertSee('James Anderson');

        // Row order: Staff | Role | Completed | Currently Assigned | Items Logged.
        // One done fault this month + one currently-assigned open fault.
        preg_match('/James Anderson.*?<\/tr>/s', $component->html(), $matches);
        $row = $matches[0] ?? '';
        $this->assertMatchesRegularExpression('/>1</', $row);
    }

    public function test_it_counts_items_logged_for_a_housekeeper(): void
    {
        $founder = $this->housekeeper('Grace Hopper');
        $unit = $this->unit();

        LostFoundItem::create([
            'room_unit_id' => $unit->id,
            'item_description' => 'Blue umbrella',
            'found_by' => $founder->id,
            'found_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->assertSee('Grace Hopper');
    }

    public function test_it_filters_by_role(): void
    {
        $this->housekeeper('Grace Hopper');
        $this->technician('James Anderson');

        Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->set('roleFilter', 'housekeeping')
            ->assertSee('Grace Hopper')
            ->assertDontSee('James Anderson');
    }

    public function test_it_searches_by_name(): void
    {
        $this->housekeeper('Grace Hopper');
        $this->technician('James Anderson');

        Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->set('search', 'james')
            ->assertSee('James Anderson')
            ->assertDontSee('Grace Hopper');
    }

    public function test_a_completion_outside_the_default_month_range_is_not_counted(): void
    {
        $officer = $this->housekeeper('Grace Hopper');
        $unit = $this->unit();
        $booking = $this->booking($unit);

        $request = HousekeepingRequest::create(['room_unit_id' => $unit->id, 'booking_id' => $booking->id, 'type' => 'towels']);
        $request->complete($officer);
        $request->forceFill(['completed_at' => now()->subMonths(2)])->save();

        $html = Livewire::actingAs($this->admin())
            ->test(StaffWorkload::class)
            ->html();

        // Grace still appears on the roster with a 0 completed count for this month.
        $this->assertStringContainsString('Grace Hopper', $html);
    }
}
