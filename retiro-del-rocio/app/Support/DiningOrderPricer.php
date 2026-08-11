<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\BarController;
use App\Http\Controllers\Api\V1\TabletController;
use App\Models\MenuCategory;
use App\Models\MenuItem;

/**
 * Prices a cart of menu items for a dining order — shared by the guest
 * tablet's Place Order flow ({@see TabletController})
 * and the Bar Tablet's POS ({@see BarController})
 * so pricing/snapshot logic exists in exactly one place.
 */
class DiningOrderPricer
{
    /** The flat room-service / table fee applied to every dining order. */
    public const SERVICE_FEE = 1000;

    /**
     * Price a cart of menu items: each item's price is read fresh from the
     * database (never trusted from the client) and snapshotted onto the
     * order, plus VAT (7.5%, on the subtotal) and the flat service fee.
     *
     * @param  array<int, array{menu_item_id:int, qty:int, note?:?string}>  $itemsInput
     * @return array{lineItems: array, subtotal: int, vat: int, serviceFee: int, total: int, itemCount: int, hasFood: bool, hasDrinks: bool}
     */
    public static function quote(array $itemsInput): array
    {
        $lineItems = [];
        $subtotal = 0;
        $itemCount = 0;
        $hasFood = false;
        $hasDrinks = false;
        // Computed once per order, not per line item — a category's
        // department (food/drink) can only be told apart via the admin's
        // MenuCategory catalog now that categories aren't a fixed list.
        $drinkSlugs = MenuCategory::drink()->pluck('slug')->all();

        foreach ($itemsInput as $entry) {
            $menuItem = MenuItem::active()->find($entry['menu_item_id']);
            abort_unless($menuItem, 404, 'One of the dishes in your order is no longer available.');

            $qty = (int) $entry['qty'];
            $subtotal += (int) $menuItem->price * $qty;
            $itemCount += $qty;

            if (in_array($menuItem->category, $drinkSlugs, true)) {
                $hasDrinks = true;
            } else {
                $hasFood = true;
            }

            $lineItems[] = [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'price' => (int) $menuItem->price,
                'qty' => $qty,
                'note' => $entry['note'] ?? null,
                // Snapshotted so the guest tablet's ETA estimate, My Orders
                // thumbnail, and the Kitchen/Bar & Lounge admin/tablet queues
                // all reflect what was true when the order was placed, not
                // today's menu (a dish's photo/prep time/category/alcohol
                // flag can change later — has_food/has_drinks and the Bar
                // Tablet's age-verification gate are derived from this same
                // snapshot, not a live lookup).
                'prep_minutes' => $menuItem->prep_minutes,
                'image_url' => $menuItem->imageUrl(),
                'category' => $menuItem->category,
                'is_alcoholic' => (bool) $menuItem->is_alcoholic,
                'voided' => false,
            ];
        }

        $vat = Vat::on($subtotal);
        $serviceFee = self::SERVICE_FEE;
        $total = $subtotal + $vat + $serviceFee;

        return compact('lineItems', 'subtotal', 'vat', 'serviceFee', 'total', 'itemCount', 'hasFood', 'hasDrinks');
    }
}
