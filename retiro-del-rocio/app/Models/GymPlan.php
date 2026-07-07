<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price', 'period', 'tagline', 'features',
        'is_featured', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'features' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function memberships()
    {
        return $this->hasMany(GymMembership::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }

    /** Short suffix shown after the price, e.g. "₦70,000 / month". */
    public function periodShort(): string
    {
        return match ($this->period) {
            'monthly', 'month' => 'month',
            'quarterly', 'quarter' => 'quarter',
            'semi-annually' => '6 months',
            'annually', 'year' => 'year',
            default => $this->period,
        };
    }

    /** How many months a membership on this plan lasts. */
    public function durationMonths(): int
    {
        return match ($this->period) {
            'quarterly', 'quarter' => 3,
            'semi-annually' => 6,
            'annually', 'year' => 12,
            default => 1,
        };
    }

    /** Features as a clean array of non-empty lines. */
    public function featureList(): array
    {
        return collect($this->features ?? [])->map(fn ($f) => trim((string) $f))->filter()->values()->all();
    }
}
