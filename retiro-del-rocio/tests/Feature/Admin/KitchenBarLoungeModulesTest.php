<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BarLounge\Menu as BarLoungeMenu;
use App\Livewire\Admin\BarLounge\Orders as BarLoungeOrders;
use App\Livewire\Admin\Kitchen\Menu as KitchenMenu;
use App\Livewire\Admin\Kitchen\Orders as KitchenOrders;
use App\Models\Booking;
use App\Models\DiningOrder;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke tests for the Kitchen and Bar & Lounge admin modules — both operate
 * on the same MenuItem/DiningOrder tables the guest tablet's Place Order and
 * My Orders screens already use, split by category (food vs `drinks`) and by
 * the `has_food`/`has_drinks` flags snapshotted onto each order at
 * checkout time.
 */
class KitchenBarLoungeModulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('super-admin');

        return $user;
    }

    private function order(bool $food, bool $drinks): DiningOrder
    {
        $booking = Booking::create([
            'reference' => 'BK-'.uniqid(),
            'customer_name' => 'Guest',
            'customer_email' => 'guest@example.test',
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'nights' => 1,
            'guests' => 1,
            'amount' => 22500,
            'status' => 'checked_in',
        ]);

        return DiningOrder::create([
            'booking_id' => $booking->id,
            'reference' => 'DN-TEST-'.uniqid(),
            'items' => [['menu_item_id' => 1, 'name' => 'Test Item', 'price' => 1000, 'qty' => 1, 'category' => $drinks && ! $food ? 'drinks' : 'mains']],
            'has_food' => $food,
            'has_drinks' => $drinks,
            'item_count' => 1,
            'subtotal' => 1000,
            'service_fee' => 1000,
            'total' => 2000,
            'customer_name' => 'Guest',
            'customer_email' => 'guest@example.com',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
        ]);
    }

    public function test_kitchen_menu_only_shows_food_items(): void
    {
        $this->actingAs($this->admin());

        MenuItem::create(['name' => 'Jollof Rice', 'slug' => 'jollof-rice', 'category' => 'mains', 'price' => 5000, 'is_active' => true, 'sort_order' => 1]);
        MenuItem::create(['name' => 'Fresh Chapman', 'slug' => 'fresh-chapman', 'category' => 'drinks', 'price' => 3500, 'is_active' => true, 'sort_order' => 2]);

        Livewire::test(KitchenMenu::class)
            ->assertSee('Jollof Rice')
            ->assertDontSee('Fresh Chapman');
    }

    public function test_bar_lounge_menu_only_shows_drink_items(): void
    {
        $this->actingAs($this->admin());

        MenuItem::create(['name' => 'Jollof Rice', 'slug' => 'jollof-rice-2', 'category' => 'mains', 'price' => 5000, 'is_active' => true, 'sort_order' => 1]);
        MenuItem::create(['name' => 'Fresh Chapman', 'slug' => 'fresh-chapman-2', 'category' => 'drinks', 'price' => 3500, 'is_active' => true, 'sort_order' => 2]);

        Livewire::test(BarLoungeMenu::class)
            ->assertSee('Fresh Chapman')
            ->assertDontSee('Jollof Rice');
    }

    public function test_kitchen_can_add_a_new_dish(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(KitchenMenu::class)
            ->call('openCreate')
            ->set('fName', 'Wagyu Tenderloin')
            ->set('fCategory', 'mains')
            ->set('fPrice', 15000)
            ->call('save');

        $this->assertDatabaseHas('menu_items', ['name' => 'Wagyu Tenderloin', 'category' => 'mains']);
    }

    public function test_bar_lounge_can_add_a_new_drink_and_it_is_always_category_drinks(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BarLoungeMenu::class)
            ->call('openCreate')
            ->set('fName', 'House Red Wine')
            ->set('fPrice', 6000)
            ->call('save');

        $this->assertDatabaseHas('menu_items', ['name' => 'House Red Wine', 'category' => 'drinks']);
    }

    public function test_kitchen_orders_only_lists_orders_with_food_items(): void
    {
        $this->actingAs($this->admin());

        $foodOrder = $this->order(food: true, drinks: false);
        $drinkOnlyOrder = $this->order(food: false, drinks: true);

        Livewire::test(KitchenOrders::class)
            ->assertSee($foodOrder->orderCode())
            ->assertDontSee($drinkOnlyOrder->orderCode());
    }

    public function test_bar_lounge_orders_only_lists_orders_with_drink_items(): void
    {
        $this->actingAs($this->admin());

        $foodOnlyOrder = $this->order(food: true, drinks: false);
        $drinkOrder = $this->order(food: false, drinks: true);

        Livewire::test(BarLoungeOrders::class)
            ->assertSee($drinkOrder->orderCode())
            ->assertDontSee($foodOnlyOrder->orderCode());
    }

    public function test_kitchen_can_advance_an_order_through_the_lifecycle(): void
    {
        $this->actingAs($this->admin());

        $order = $this->order(food: true, drinks: false);

        Livewire::test(KitchenOrders::class)
            ->call('advanceStatus', $order->id);

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_a_mixed_order_containing_food_and_drinks_appears_in_both_queues(): void
    {
        $this->actingAs($this->admin());

        $mixed = $this->order(food: true, drinks: true);

        Livewire::test(KitchenOrders::class)->assertSee($mixed->orderCode());
        Livewire::test(BarLoungeOrders::class)->assertSee($mixed->orderCode());
    }

    public function test_kitchen_can_add_a_new_category_and_use_it_for_a_dish(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(KitchenMenu::class)
            ->call('openCategoryManager')
            ->set('newCategoryName', 'Grills')
            ->call('addCategory')
            ->assertSee('Grills');

        $this->assertDatabaseHas('menu_categories', ['slug' => 'grills', 'department' => 'food']);

        Livewire::test(KitchenMenu::class)
            ->call('openCreate')
            ->set('fName', 'Smoked Ribs')
            ->set('fCategory', 'grills')
            ->set('fPrice', 9500)
            ->call('save');

        $this->assertDatabaseHas('menu_items', ['name' => 'Smoked Ribs', 'category' => 'grills']);
    }

    public function test_bar_lounge_can_add_a_new_category_and_use_it_for_a_drink(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(BarLoungeMenu::class)
            ->call('openCategoryManager')
            ->set('newCategoryName', 'Cocktails')
            ->call('addCategory')
            ->assertSee('Cocktails');

        $this->assertDatabaseHas('menu_categories', ['slug' => 'cocktails', 'department' => 'drink']);

        Livewire::test(BarLoungeMenu::class)
            ->call('openCreate')
            ->set('fName', 'Old Fashioned')
            ->set('fCategory', 'cocktails')
            ->set('fPrice', 7000)
            ->call('save');

        $this->assertDatabaseHas('menu_items', ['name' => 'Old Fashioned', 'category' => 'cocktails']);

        // A drink in the new category still lands in the Bar & Lounge orders
        // queue like any other, since department is derived from the
        // category catalog, not a hardcoded 'drinks' string.
        Livewire::test(BarLoungeMenu::class)->assertSee('Old Fashioned');
    }

    public function test_a_category_still_in_use_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());

        MenuItem::create(['name' => 'Jollof Rice', 'slug' => 'jollof-rice-3', 'category' => 'mains', 'price' => 5000, 'is_active' => true, 'sort_order' => 1]);
        $mains = MenuCategory::food()->where('slug', 'mains')->firstOrFail();

        Livewire::test(KitchenMenu::class)->call('deleteCategory', $mains->id);

        $this->assertDatabaseHas('menu_categories', ['id' => $mains->id]);
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $category = MenuCategory::create(['name' => 'Seasonal', 'slug' => 'seasonal', 'department' => 'food', 'sort_order' => 99]);

        Livewire::test(KitchenMenu::class)->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_kitchen_cannot_delete_a_bar_lounge_category(): void
    {
        $this->actingAs($this->admin());

        $drinks = MenuCategory::drink()->where('slug', 'drinks')->firstOrFail();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(KitchenMenu::class)->call('deleteCategory', $drinks->id);
    }

    public function test_the_admin_routes_are_reachable(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.kitchen.menu'))->assertOk();
        $this->get(route('admin.kitchen.orders'))->assertOk();
        $this->get(route('admin.bar-lounge.menu'))->assertOk();
        $this->get(route('admin.bar-lounge.orders'))->assertOk();
    }
}
