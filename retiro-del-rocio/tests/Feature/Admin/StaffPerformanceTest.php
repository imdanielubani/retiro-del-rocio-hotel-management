<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BarLounge\StaffPerformance as BarStaffPerformance;
use App\Livewire\Admin\Kitchen\StaffPerformance as KitchenStaffPerformance;
use App\Models\BarTab;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin → Bar & Lounge/Kitchen → Staff — per-individual sales/tickets
 * tracking, so multiple accounts holding the same tablet role (e.g. two
 * `bar` waiters) show up as separate rows rather than one lumped total.
 */
class StaffPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('super-admin');

        return $user;
    }

    private function barStaff(string $name): User
    {
        Role::findOrCreate('bar', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('bar');

        return $user;
    }

    private function kitchenStaff(string $name): User
    {
        Role::findOrCreate('kitchen', 'web');
        $user = User::factory()->create(['status' => 'active', 'name' => $name]);
        $user->assignRole('kitchen');

        return $user;
    }

    public function test_the_bar_staff_screen_shows_no_role_created_yet_without_erroring(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BarStaffPerformance::class)
            ->assertOk()
            ->assertSee('No bar staff yet');
    }

    public function test_two_bar_staff_are_credited_with_their_own_settled_tabs_separately(): void
    {
        $this->actingAs($this->admin());

        $bar1 = $this->barStaff('Bar 1');
        $bar2 = $this->barStaff('Bar 2');

        BarTab::create([
            'code' => 'TAB-1', 'assigned_to' => $bar1->id, 'opened_by' => $bar1->id,
            'status' => 'settled', 'subtotal' => 4000, 'vat' => 300, 'service_fee' => 0, 'total' => 4300,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'settled_at' => now(),
        ]);
        BarTab::create([
            'code' => 'TAB-2', 'assigned_to' => $bar1->id, 'opened_by' => $bar1->id,
            'status' => 'settled', 'subtotal' => 2000, 'vat' => 150, 'service_fee' => 0, 'total' => 2150,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'settled_at' => now(),
        ]);
        BarTab::create([
            'code' => 'TAB-3', 'assigned_to' => $bar2->id, 'opened_by' => $bar2->id,
            'status' => 'settled', 'subtotal' => 1000, 'vat' => 75, 'service_fee' => 0, 'total' => 1075,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'settled_at' => now(),
        ]);
        // An open tab isn't revenue yet — excluded from the settled total.
        BarTab::create([
            'code' => 'TAB-4', 'assigned_to' => $bar1->id, 'opened_by' => $bar1->id,
            'status' => 'open', 'subtotal' => 9000, 'vat' => 675, 'service_fee' => 0, 'total' => 9675,
        ]);

        $component = Livewire::test(BarStaffPerformance::class)->set('range', 'all');

        $component->assertSeeInOrder(['Bar 1', 'Bar 2']);
        $component->assertSee('₦6,450'); // Bar 1: 4300 + 2150
        $component->assertSee('₦1,075'); // Bar 2
    }

    public function test_a_walk_in_order_with_no_bar_tab_does_not_count_toward_any_staff_member(): void
    {
        $this->actingAs($this->admin());
        $this->barStaff('Bar 1');

        Livewire::test(BarStaffPerformance::class)
            ->set('range', 'all')
            ->assertSee('₦0');
    }

    public function test_the_kitchen_staff_screen_shows_no_role_created_yet_without_erroring(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(KitchenStaffPerformance::class)
            ->assertOk()
            ->assertSee('No kitchen staff yet');
    }

    public function test_two_kitchen_staff_are_credited_with_their_own_served_tickets_separately(): void
    {
        $this->actingAs($this->admin());

        $chef1 = $this->kitchenStaff('Chef 1');
        $chef2 = $this->kitchenStaff('Chef 2');

        DiningOrder::create([
            'reference' => 'DN-1', 'items' => [['menu_item_id' => 1, 'name' => 'Jollof', 'price' => 5000, 'qty' => 1]],
            'has_food' => true, 'has_drinks' => false, 'item_count' => 1,
            'subtotal' => 5000, 'vat' => 0, 'service_fee' => 0, 'total' => 5000,
            'status' => 'delivered', 'payment_status' => 'paid', 'assigned_to' => $chef1->id,
        ]);
        DiningOrder::create([
            'reference' => 'DN-2', 'items' => [['menu_item_id' => 2, 'name' => 'Suya', 'price' => 3000, 'qty' => 1]],
            'has_food' => true, 'has_drinks' => false, 'item_count' => 1,
            'subtotal' => 3000, 'vat' => 0, 'service_fee' => 0, 'total' => 3000,
            'status' => 'delivered', 'payment_status' => 'paid', 'assigned_to' => $chef2->id,
        ]);
        // Not yet served — shouldn't count toward "tickets served".
        DiningOrder::create([
            'reference' => 'DN-3', 'items' => [['menu_item_id' => 3, 'name' => 'Rice', 'price' => 4000, 'qty' => 1]],
            'has_food' => true, 'has_drinks' => false, 'item_count' => 1,
            'subtotal' => 4000, 'vat' => 0, 'service_fee' => 0, 'total' => 4000,
            'status' => 'preparing', 'payment_status' => 'pending', 'assigned_to' => $chef1->id,
        ]);

        $component = Livewire::test(KitchenStaffPerformance::class)->set('range', 'all');

        $component->assertSeeInOrder(['Chef 1', 'Chef 2']);
        $component->assertSee('₦5,000');
        $component->assertSee('₦3,000');
    }
}
