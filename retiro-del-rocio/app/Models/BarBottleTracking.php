<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An individual bottle's open/unopened state and how much of it is left — separate from the item's whole-unit stock count. */
class BarBottleTracking extends Model
{
    protected $attributes = [
        'opened' => false,
        'remaining_percent' => 100,
    ];

    protected $fillable = [
        'bar_inventory_item_id', 'bottle_size', 'opened', 'remaining_percent',
    ];

    protected $casts = [
        'opened' => 'boolean',
        'remaining_percent' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(BarInventoryItem::class, 'bar_inventory_item_id');
    }

    public function isEmpty(): bool
    {
        return $this->remaining_percent <= 0;
    }

    public function status(): string
    {
        if ($this->isEmpty()) {
            return 'empty';
        }

        return $this->opened ? 'opened' : 'unopened';
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            'empty' => 'Empty',
            'opened' => 'Opened',
            default => 'Unopened',
        };
    }
}
