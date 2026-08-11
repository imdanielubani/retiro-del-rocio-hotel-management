<?php

namespace Database\Seeders;

use App\Models\BarBottleTracking;
use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Illuminate\Database\Seeder;

/** Demo Nigerian bar stock — beers, spirits, and soft drinks — so the Bar Inventory module has something real to look at. */
class BarInventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Star Lager Beer', 'category' => 'Beer', 'sku' => 'BEER-STAR-60CL', 'unit' => 'bottle', 'brand' => 'Nigerian Breweries', 'supplier' => 'Nigerian Breweries Plc', 'cost_price' => 450, 'selling_price' => 1000, 'current_stock' => 96, 'minimum_stock_level' => 24],
            ['name' => 'Gulder Lager Beer', 'category' => 'Beer', 'sku' => 'BEER-GULD-60CL', 'unit' => 'bottle', 'brand' => 'Nigerian Breweries', 'supplier' => 'Nigerian Breweries Plc', 'cost_price' => 470, 'selling_price' => 1000, 'current_stock' => 72, 'minimum_stock_level' => 24],
            ['name' => 'Guinness Foreign Extra Stout', 'category' => 'Beer', 'sku' => 'BEER-GUIN-60CL', 'unit' => 'bottle', 'brand' => 'Guinness', 'supplier' => 'Guinness Nigeria Plc', 'cost_price' => 500, 'selling_price' => 1100, 'current_stock' => 18, 'minimum_stock_level' => 24],
            ['name' => 'Heineken Lager Beer', 'category' => 'Beer', 'sku' => 'BEER-HEIN-60CL', 'unit' => 'bottle', 'brand' => 'Heineken', 'supplier' => 'Nigerian Breweries Plc', 'cost_price' => 550, 'selling_price' => 1200, 'current_stock' => 0, 'minimum_stock_level' => 12],
            ['name' => 'Trophy Lager Beer', 'category' => 'Beer', 'sku' => 'BEER-TROP-60CL', 'unit' => 'bottle', 'brand' => 'International Breweries', 'supplier' => 'International Breweries Plc', 'cost_price' => 380, 'selling_price' => 900, 'current_stock' => 48, 'minimum_stock_level' => 24],
            ['name' => 'Hennessy VS Cognac', 'category' => 'Spirits', 'sku' => 'SPRT-HENN-70CL', 'unit' => 'bottle', 'brand' => 'Hennessy', 'supplier' => 'Moet Hennessy Nigeria', 'cost_price' => 45000, 'selling_price' => 75000, 'current_stock' => 6, 'minimum_stock_level' => 3],
            ['name' => 'Chelsea Dry Gin', 'category' => 'Spirits', 'sku' => 'SPRT-CHEL-75CL', 'unit' => 'bottle', 'brand' => 'Chelsea', 'supplier' => 'Jubaili Bros', 'cost_price' => 3500, 'selling_price' => 7000, 'current_stock' => 14, 'minimum_stock_level' => 6],
            ['name' => "Seaman's Aromatic Schnapps", 'category' => 'Spirits', 'sku' => 'SPRT-SEAM-72CL', 'unit' => 'bottle', 'brand' => "Seaman's Schnapps", 'supplier' => 'Grand Oak Limited', 'cost_price' => 2800, 'selling_price' => 5500, 'current_stock' => 20, 'minimum_stock_level' => 6],
            ['name' => 'Smirnoff Vodka', 'category' => 'Spirits', 'sku' => 'SPRT-SMIR-75CL', 'unit' => 'bottle', 'brand' => 'Smirnoff', 'supplier' => 'International Breweries Plc', 'cost_price' => 8000, 'selling_price' => 15000, 'current_stock' => 3, 'minimum_stock_level' => 5],
            ['name' => 'Smirnoff Ice', 'category' => 'RTD / Malt', 'sku' => 'RTD-SMIC-33CL', 'unit' => 'can', 'brand' => 'Smirnoff', 'supplier' => 'International Breweries Plc', 'cost_price' => 600, 'selling_price' => 1300, 'current_stock' => 60, 'minimum_stock_level' => 24],
            ['name' => 'Coca-Cola', 'category' => 'Soft Drink', 'sku' => 'SOFT-COKE-35CL', 'unit' => 'bottle', 'brand' => 'Coca-Cola', 'supplier' => 'Nigerian Bottling Company', 'cost_price' => 200, 'selling_price' => 500, 'current_stock' => 120, 'minimum_stock_level' => 48],
            ['name' => 'Schweppes Tonic Water', 'category' => 'Mixer', 'sku' => 'MIX-SCHW-33CL', 'unit' => 'bottle', 'brand' => 'Schweppes', 'supplier' => 'Nigerian Bottling Company', 'cost_price' => 250, 'selling_price' => 600, 'current_stock' => 40, 'minimum_stock_level' => 24],
            ['name' => 'Chivita 100% Orange Juice', 'category' => 'Juice', 'sku' => 'JUIC-CHIV-1L', 'unit' => 'carton', 'brand' => 'Chivita', 'supplier' => 'Chi Limited', 'cost_price' => 900, 'selling_price' => 1800, 'current_stock' => 10, 'minimum_stock_level' => 12],
            ['name' => 'Malta Guinness', 'category' => 'Non-Alcoholic Malt', 'sku' => 'MALT-GUIN-33CL', 'unit' => 'bottle', 'brand' => 'Guinness', 'supplier' => 'Guinness Nigeria Plc', 'cost_price' => 300, 'selling_price' => 700, 'current_stock' => 30, 'minimum_stock_level' => 24],
        ];

        $saved = [];
        foreach ($items as $data) {
            $saved[$data['name']] = BarInventoryItem::updateOrCreate(['sku' => $data['sku']], $data);
        }

        // A handful of stock movements so the Dashboard and each ledger screen have something to show.
        $star = $saved['Star Lager Beer'];
        $hennessy = $saved['Hennessy VS Cognac'];
        $smirnoffVodka = $saved['Smirnoff Vodka'];
        $guinness = $saved['Guinness Foreign Extra Stout'];

        if ($star->movements()->count() === 0) {
            BarStockMovement::create([
                'bar_inventory_item_id' => $star->id,
                'type' => BarStockMovement::IN,
                'quantity' => 48,
                'unit_cost' => 450,
                'supplier' => 'Nigerian Breweries Plc',
                'reference' => 'INV-NB-20452',
                'occurred_at' => now()->subDays(3),
            ]);

            BarStockMovement::create([
                'bar_inventory_item_id' => $star->id,
                'type' => BarStockMovement::OUT,
                'quantity' => 12,
                'reason' => 'sale',
                'linked_order' => 'Lounge / Table 6',
                'staff_name' => 'Ngozi Eze',
                'occurred_at' => now()->subHours(6),
            ]);
        }

        if ($hennessy->movements()->count() === 0) {
            BarStockMovement::create([
                'bar_inventory_item_id' => $hennessy->id,
                'type' => BarStockMovement::OUT,
                'quantity' => 1,
                'reason' => 'complimentary',
                'staff_name' => 'Tunde Bakare',
                'occurred_at' => now()->subDay(),
            ]);
        }

        if ($guinness->movements()->count() === 0) {
            BarStockMovement::create([
                'bar_inventory_item_id' => $guinness->id,
                'type' => BarStockMovement::ADJUSTMENT_DECREASE,
                'quantity' => 2,
                'reason' => 'Breakage during restock',
                'notes' => '2 bottles broke while moving crates from the store room.',
                'occurred_at' => now()->subHours(20),
            ]);
        }

        if ($smirnoffVodka->movements()->count() === 0) {
            BarStockMovement::create([
                'bar_inventory_item_id' => $smirnoffVodka->id,
                'type' => BarStockMovement::IN,
                'quantity' => 5,
                'unit_cost' => 8000,
                'supplier' => 'International Breweries Plc',
                'reference' => 'INV-IB-88231',
                'occurred_at' => now()->subDays(10),
            ]);
        }

        // Bottle tracking for the two spirits guests are served by the glass.
        if ($hennessy->bottleTrackings()->count() === 0) {
            BarBottleTracking::create(['bar_inventory_item_id' => $hennessy->id, 'bottle_size' => '70cl', 'opened' => true, 'remaining_percent' => 35]);
            BarBottleTracking::create(['bar_inventory_item_id' => $hennessy->id, 'bottle_size' => '70cl', 'opened' => false, 'remaining_percent' => 100]);
        }

        if ($smirnoffVodka->bottleTrackings()->count() === 0) {
            BarBottleTracking::create(['bar_inventory_item_id' => $smirnoffVodka->id, 'bottle_size' => '75cl', 'opened' => true, 'remaining_percent' => 5]);
        }
    }
}
