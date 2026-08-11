<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single stock-ledger entry against a BarInventoryItem — stock received,
 * stock issued (sale, damage, complimentary, transfer, expired), or a manual
 * adjustment. `BarInventoryItem::current_stock` is only ever changed from
 * here (see booted() below), so every screen that moves stock — Stock In,
 * Stock Out, Consumption Tracking, Adjustments — writes through the same
 * single source of truth instead of each mutating the counter itself.
 */
class BarStockMovement extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    public const ADJUSTMENT_INCREASE = 'adjustment_increase';

    public const ADJUSTMENT_DECREASE = 'adjustment_decrease';

    public const OUT_REASONS = ['sale', 'damage', 'complimentary', 'transfer', 'expired'];

    protected $attributes = [
        'quantity' => 1,
    ];

    protected $fillable = [
        'bar_inventory_item_id', 'type', 'quantity', 'reason', 'unit_cost',
        'reference', 'supplier', 'staff_name', 'linked_order', 'notes', 'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'integer',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            $movement->occurred_at ??= now();
        });

        static::created(function (self $movement) {
            $item = $movement->item()->first();
            if (! $item) {
                return;
            }

            $delta = match ($movement->type) {
                self::IN, self::ADJUSTMENT_INCREASE => $movement->quantity,
                self::OUT, self::ADJUSTMENT_DECREASE => -$movement->quantity,
                default => 0,
            };

            // Clamp at zero — stock can't go negative even if an "out" movement outpaces what's on hand.
            $item->update(['current_stock' => max(0, $item->current_stock + $delta)]);
        });
    }

    public function item()
    {
        return $this->belongsTo(BarInventoryItem::class, 'bar_inventory_item_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::IN => 'Stock In',
            self::OUT => 'Stock Out',
            self::ADJUSTMENT_INCREASE => 'Adjustment (+)',
            self::ADJUSTMENT_DECREASE => 'Adjustment (-)',
            default => ucfirst($this->type),
        };
    }

    public function reasonLabel(): string
    {
        return $this->reason ? ucfirst(str_replace('_', ' ', $this->reason)) : '—';
    }

    public function isConsumption(): bool
    {
        return $this->type === self::OUT && $this->reason === 'sale';
    }
}
