<?php

namespace App\Models;

use App\Support\Webp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SpaService extends Model
{
    protected $fillable = [
        'name', 'slug', 'spa_category_id', 'price', 'duration_minutes', 'description', 'image', 'icon', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(SpaCategory::class, 'spa_category_id');
    }

    public function durationLabel(): ?string
    {
        return $this->duration_minutes ? $this->duration_minutes.' min' : null;
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

    /**
     * The payload the guest tablet's Spa & Wellness screen renders. Assumes
     * `category` is already eager-loaded (see {@see TabletController::spaServices}) —
     * touching it here otherwise N+1 queries one category lookup per service.
     */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (int) $this->price,
            'price_label' => 'NGN '.number_format((int) $this->price),
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->durationLabel(),
            'image_url' => $this->imageUrl(),
            'category_id' => $this->spa_category_id,
            'category' => $this->category?->name,
            'category_color' => $this->category?->color ?? '#6b7280',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->resolve($this->image);
    }

    public function iconUrl(): ?string
    {
        return $this->resolve($this->icon);
    }

    protected function resolve(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'images/')) {
            return Webp::url(str_replace(' ', '%20', asset($path)));
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Webp::url(Storage::disk('public')->url($path));
    }
}
