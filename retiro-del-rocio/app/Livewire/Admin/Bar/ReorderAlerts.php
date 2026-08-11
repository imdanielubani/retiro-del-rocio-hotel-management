<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use Livewire\Component;

/** Admin → Bar Inventory → Reorder Alerts — every item at or below its minimum stock level, with a suggested reorder quantity. */
class ReorderAlerts extends Component
{
    public function render()
    {
        $items = BarInventoryItem::all()->filter->needsRestock()->sortBy('current_stock')->values();

        $stats = [
            ['label' => 'Items Requiring Restock', 'value' => $items->count(), 'sub' => 'At or below minimum stock', 'accent' => '#dc2626'],
            ['label' => 'Out of Stock', 'value' => $items->filter->isOutOfStock()->count(), 'sub' => 'Needs immediate reorder', 'accent' => '#dc2626'],
            ['label' => 'Suggested Reorder Units', 'value' => (int) $items->sum(fn ($i) => $i->reorderSuggestion()), 'sub' => 'Across every low item', 'accent' => '#f38c00'],
        ];

        return view('admin.bar.reorder-alerts', [
            'items' => $items,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Bar Reorder Alerts',
            'subtitle' => 'Everything at or below its minimum stock level',
        ]);
    }
}
