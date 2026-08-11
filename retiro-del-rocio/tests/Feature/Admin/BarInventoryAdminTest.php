<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Bar\Adjustments;
use App\Livewire\Admin\Bar\BottleTracking;
use App\Livewire\Admin\Bar\Consumption;
use App\Livewire\Admin\Bar\Dashboard;
use App\Livewire\Admin\Bar\Items;
use App\Livewire\Admin\Bar\ReorderAlerts;
use App\Livewire\Admin\Bar\StockIn;
use App\Livewire\Admin\Bar\StockOut;
use App\Models\BarBottleTracking;
use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Bar Inventory — the model-level stock ledger (BarStockMovement is
 * the only thing allowed to change BarInventoryItem::current_stock) plus a
 * smoke test of every screen: Dashboard, Items, Stock In, Stock Out, Bottle
 * Tracking, Consumption Tracking, Adjustments and Reorder Alerts.
 */
class BarInventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function item(array $overrides = []): BarInventoryItem
    {
        return BarInventoryItem::create(array_merge([
            'name' => 'Jack Daniels',
            'category' => 'Spirits',
            'unit' => 'bottle',
            'cost_price' => 15000,
            'selling_price' => 25000,
            'current_stock' => 10,
            'minimum_stock_level' => 5,
        ], $overrides));
    }

    /* ---------------------------------------------------------------
     | Model ledger behaviour
     |------------------------------------------------------------- */

    public function test_a_stock_in_movement_increases_current_stock(): void
    {
        $item = $this->item(['current_stock' => 10]);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::IN,
            'quantity' => 5,
            'occurred_at' => now(),
        ]);

        $this->assertSame(15, $item->fresh()->current_stock);
    }

    public function test_a_stock_out_movement_decreases_current_stock(): void
    {
        $item = $this->item(['current_stock' => 10]);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::OUT,
            'quantity' => 4,
            'reason' => 'sale',
            'occurred_at' => now(),
        ]);

        $this->assertSame(6, $item->fresh()->current_stock);
    }

    public function test_stock_cannot_go_negative_when_issuing_more_than_is_on_hand(): void
    {
        $item = $this->item(['current_stock' => 2]);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::OUT,
            'quantity' => 10,
            'reason' => 'damage',
            'occurred_at' => now(),
        ]);

        $this->assertSame(0, $item->fresh()->current_stock);
    }

    public function test_adjustments_increase_and_decrease_stock(): void
    {
        $item = $this->item(['current_stock' => 10]);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::ADJUSTMENT_INCREASE,
            'quantity' => 3,
            'reason' => 'count correction',
            'occurred_at' => now(),
        ]);
        $this->assertSame(13, $item->fresh()->current_stock);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::ADJUSTMENT_DECREASE,
            'quantity' => 5,
            'reason' => 'breakage',
            'occurred_at' => now(),
        ]);
        $this->assertSame(8, $item->fresh()->current_stock);
    }

    public function test_item_status_reflects_stock_against_minimum(): void
    {
        $inStock = $this->item(['current_stock' => 20, 'minimum_stock_level' => 5]);
        $lowStock = $this->item(['name' => 'Gordon\'s Gin', 'current_stock' => 3, 'minimum_stock_level' => 5]);
        $outOfStock = $this->item(['name' => 'Baileys', 'current_stock' => 0, 'minimum_stock_level' => 5]);

        $this->assertSame(BarInventoryItem::IN_STOCK, $inStock->status());
        $this->assertSame(BarInventoryItem::LOW_STOCK, $lowStock->status());
        $this->assertSame(BarInventoryItem::OUT_OF_STOCK, $outOfStock->status());
        $this->assertTrue($lowStock->needsRestock());
        $this->assertTrue($outOfStock->needsRestock());
        $this->assertFalse($inStock->needsRestock());
    }

    public function test_reorder_suggestion_tops_up_to_double_the_minimum(): void
    {
        $item = $this->item(['current_stock' => 2, 'minimum_stock_level' => 5]);

        $this->assertSame(8, $item->reorderSuggestion());
    }

    /* ---------------------------------------------------------------
     | Screens
     |------------------------------------------------------------- */

    public function test_dashboard_shows_stock_stats(): void
    {
        $this->item(['current_stock' => 20, 'minimum_stock_level' => 5]);
        $this->item(['name' => 'Baileys', 'current_stock' => 0, 'minimum_stock_level' => 5]);

        Livewire::actingAs($this->admin())
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Total Inventory Items')
            ->assertSee('Out of Stock Items');
    }

    public function test_items_lists_filters_and_creates(): void
    {
        $this->item(['name' => 'Jack Daniels']);
        $this->item(['name' => 'Baileys', 'current_stock' => 0]);

        Livewire::actingAs($this->admin())
            ->test(Items::class)
            ->assertSee('Jack Daniels')
            ->assertSee('Baileys')
            ->set('statusFilter', 'out_of_stock')
            ->assertSee('Baileys')
            ->assertDontSee('Jack Daniels');

        Livewire::actingAs($this->admin())
            ->test(Items::class)
            ->set('fName', 'Smirnoff Vodka')
            ->set('fUnit', 'bottle')
            ->set('fCostPrice', 8000)
            ->set('fSellingPrice', 15000)
            ->set('fCurrentStock', 12)
            ->set('fMinimumStockLevel', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bar_inventory_items', ['name' => 'Smirnoff Vodka', 'current_stock' => 12]);
    }

    public function test_items_edit_and_delete(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->admin())
            ->test(Items::class)
            ->call('openEdit', $item->id)
            ->set('fName', 'Hennessy VSOP')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Hennessy VSOP', $item->fresh()->name);

        Livewire::actingAs($this->admin())
            ->test(Items::class)
            ->call('delete', $item->id);

        $this->assertDatabaseMissing('bar_inventory_items', ['id' => $item->id]);
    }

    public function test_stock_in_creates_a_movement_and_raises_stock(): void
    {
        $item = $this->item(['current_stock' => 5]);

        Livewire::actingAs($this->admin())
            ->test(StockIn::class)
            ->set('fItemId', $item->id)
            ->set('fQuantity', 10)
            ->set('fUnitCost', 16000)
            ->set('fSupplier', 'Prime Distributors')
            ->set('fReference', 'INV-2201')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15, $item->fresh()->current_stock);
        $this->assertSame(16000, $item->fresh()->cost_price);
        $this->assertDatabaseHas('bar_stock_movements', ['bar_inventory_item_id' => $item->id, 'type' => 'in', 'reference' => 'INV-2201']);
    }

    public function test_stock_out_creates_a_movement_and_excludes_sales_from_its_own_list(): void
    {
        $item = $this->item(['current_stock' => 10]);

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => BarStockMovement::OUT,
            'quantity' => 1,
            'reason' => 'sale',
            'occurred_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(StockOut::class)
            ->set('fItemId', $item->id)
            ->set('fQuantity', 2)
            ->set('fReason', 'damage')
            ->set('fStaffName', 'Musa')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Musa');

        // The sale movement created above must not appear in the Stock Out ledger — only the "damage" one just logged.
        $this->assertSame(1, $component->viewData('movements')->total());

        $this->assertSame(7, $item->fresh()->current_stock);
    }

    public function test_consumption_logs_a_sale_linked_to_an_order(): void
    {
        $item = $this->item(['current_stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test(Consumption::class)
            ->set('fItemId', $item->id)
            ->set('fQuantity', 2)
            ->set('fLinkedOrder', 'Table 4 / Order #128')
            ->set('fStaffName', 'Ada')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Table 4 / Order #128');

        $this->assertSame(8, $item->fresh()->current_stock);
        $this->assertDatabaseHas('bar_stock_movements', [
            'bar_inventory_item_id' => $item->id,
            'reason' => 'sale',
            'linked_order' => 'Table 4 / Order #128',
        ]);
    }

    public function test_adjustments_screen_logs_an_increase_and_a_decrease(): void
    {
        $item = $this->item(['current_stock' => 10]);

        Livewire::actingAs($this->admin())
            ->test(Adjustments::class)
            ->set('fItemId', $item->id)
            ->set('fDirection', 'decrease')
            ->set('fQuantity', 3)
            ->set('fReason', 'Stock count correction')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7, $item->fresh()->current_stock);
    }

    public function test_bottle_tracking_creates_edits_and_deletes(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->admin())
            ->test(BottleTracking::class)
            ->set('fItemId', $item->id)
            ->set('fBottleSize', '750ml')
            ->set('fOpened', true)
            ->set('fRemainingPercent', 40)
            ->call('save')
            ->assertHasNoErrors();

        $bottle = BarBottleTracking::first();
        $this->assertNotNull($bottle);
        $this->assertSame('opened', $bottle->status());

        Livewire::actingAs($this->admin())
            ->test(BottleTracking::class)
            ->call('openEdit', $bottle->id)
            ->set('fRemainingPercent', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('empty', $bottle->fresh()->status());

        Livewire::actingAs($this->admin())
            ->test(BottleTracking::class)
            ->call('delete', $bottle->id);

        $this->assertDatabaseMissing('bar_bottle_trackings', ['id' => $bottle->id]);
    }

    public function test_reorder_alerts_lists_only_items_at_or_below_minimum(): void
    {
        $this->item(['name' => 'Well Stocked', 'current_stock' => 50, 'minimum_stock_level' => 5]);
        $this->item(['name' => 'Needs Reorder', 'current_stock' => 2, 'minimum_stock_level' => 5]);

        Livewire::actingAs($this->admin())
            ->test(ReorderAlerts::class)
            ->assertSee('Needs Reorder')
            ->assertDontSee('Well Stocked');
    }
}
