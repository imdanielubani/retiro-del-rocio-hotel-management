<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A stocked bar product — spirits, mixers, garnish, anything the bar draws down as it sells. */
class BarInventoryItem extends Model
{
    public const IN_STOCK = 'in_stock';

    public const LOW_STOCK = 'low_stock';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const UNITS = ['bottle', 'can', 'carton', 'ml', 'litre'];

    protected $attributes = [
        'unit' => 'bottle',
        'cost_price' => 0,
        'selling_price' => 0,
        'current_stock' => 0,
        'minimum_stock_level' => 0,
    ];

    protected $fillable = [
        'name', 'category', 'sku', 'unit', 'brand', 'supplier',
        'cost_price', 'selling_price', 'current_stock', 'minimum_stock_level',
    ];

    protected $casts = [
        'cost_price' => 'integer',
        'selling_price' => 'integer',
        'current_stock' => 'integer',
        'minimum_stock_level' => 'integer',
    ];

    public function movements()
    {
        return $this->hasMany(BarStockMovement::class);
    }

    public function bottleTrackings()
    {
        return $this->hasMany(BarBottleTracking::class);
    }

    public function status(): string
    {
        if ($this->current_stock <= 0) {
            return self::OUT_OF_STOCK;
        }

        if ($this->current_stock <= $this->minimum_stock_level) {
            return self::LOW_STOCK;
        }

        return self::IN_STOCK;
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::OUT_OF_STOCK => 'Out of Stock',
            self::LOW_STOCK => 'Low Stock',
            default => 'In Stock',
        };
    }

    public function isLowStock(): bool
    {
        return $this->status() === self::LOW_STOCK;
    }

    public function isOutOfStock(): bool
    {
        return $this->status() === self::OUT_OF_STOCK;
    }

    public function needsRestock(): bool
    {
        return $this->current_stock <= $this->minimum_stock_level;
    }

    /** A simple top-up-to-double-the-minimum suggestion — enough headroom to clear the reorder alert without over-ordering. */
    public function reorderSuggestion(): int
    {
        if (! $this->needsRestock()) {
            return 0;
        }

        return max(1, ($this->minimum_stock_level * 2) - $this->current_stock);
    }

    public function stockValue(): int
    {
        return $this->current_stock * $this->cost_price;
    }

    public function unitLabel(): string
    {
        return match ($this->unit) {
            'ml' => 'ml',
            'litre' => 'Litre',
            default => ucfirst($this->unit),
        };
    }

    public static function naira(int $amount): string
    {
        return '₦'.number_format($amount);
    }
}
