<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Livewire\Component;

/** Admin → Bar Inventory → Dashboard — the bar's stock position at a glance. */
class Dashboard extends Component
{
    public function render()
    {
        $items = BarInventoryItem::all();

        $stats = [
            ['label' => 'Total Inventory Items', 'value' => $items->count(), 'sub' => 'Products tracked', 'accent' => '#f38c00'],
            ['label' => 'Low Stock Items', 'value' => $items->filter->isLowStock()->count(), 'sub' => 'At or below minimum', 'accent' => '#d97706'],
            ['label' => 'Out of Stock Items', 'value' => $items->filter->isOutOfStock()->count(), 'sub' => 'Nothing on hand', 'accent' => '#dc2626'],
            ['label' => 'Total Inventory Value', 'value' => BarInventoryItem::naira((int) $items->sum(fn ($i) => $i->stockValue())), 'sub' => 'At cost price', 'accent' => '#16a34a'],
        ];

        $recentMovements = BarStockMovement::query()->with('item')->latest('occurred_at')->latest('id')->limit(10)->get();

        return view('admin.bar.dashboard', [
            'stats' => $stats,
            'recentMovements' => $recentMovements,
        ])->layout('components.admin.app', [
            'title' => 'Bar Inventory Dashboard',
            'subtitle' => 'The bar\'s stock position at a glance',
        ]);
    }
}
