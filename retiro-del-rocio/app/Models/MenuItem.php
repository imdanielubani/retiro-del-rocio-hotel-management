<?php

namespace App\Models;

use App\Support\Webp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'price', 'prep_minutes', 'description', 'image', 'is_active', 'is_alcoholic', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'prep_minutes' => 'integer',
        'is_active' => 'boolean',
        'is_alcoholic' => 'boolean',
        'sort_order' => 'integer',
    ];

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

    /** Everything the Kitchen admin manages — items in a `food` department category. */
    public function scopeFood($query)
    {
        return $query->whereIn('category', MenuCategory::food()->pluck('slug'));
    }

    /** Everything the Bar & Lounge admin manages — items in a `drink` department category. */
    public function scopeDrinks($query)
    {
        return $query->whereIn('category', MenuCategory::drink()->pluck('slug'));
    }

    public function categoryLabel(): string
    {
        return MenuCategory::where('slug', $this->category)->value('name')
            ?? ucfirst(str_replace('-', ' ', $this->category));
    }

    /** 'food' or 'drink' — the department its category belongs to. */
    public function department(): string
    {
        return MenuCategory::where('slug', $this->category)->value('department') ?? 'drink';
    }

    public function prepLabel(): ?string
    {
        return $this->prep_minutes ? $this->prep_minutes.' mins' : null;
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }

    /** The payload the guest tablet's Place Order menu renders. */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'department' => $this->department(),
            'price' => (int) $this->price,
            'price_label' => 'NGN '.number_format((int) $this->price),
            'prep_minutes' => $this->prep_minutes,
            'prep_label' => $this->prepLabel(),
            'image_url' => $this->imageUrl(),
            'is_alcoholic' => (bool) $this->is_alcoholic,
            'is_active' => (bool) $this->is_active,
        ];
    }

    public function imageUrl(): ?string
    {
        return self::resolveImagePath($this->image);
    }

    /**
     * Turn a raw stored image path (the `image` column's value, e.g.
     * "menu-items/xyz.jpg") into an absolute, host-correct URL — resolved
     * fresh against today's `APP_URL`/storage disk rather than trusted from
     * anywhere it may have been cached or snapshotted, so it keeps working
     * even if the app's base URL changes later (e.g. a dev machine's LAN IP,
     * or a domain migration) {@see DiningOrderPricer::quote()}, which
     * snapshots this raw path rather than a pre-resolved URL for exactly
     * this reason.
     */
    public static function resolveImagePath(?string $path): ?string
    {
        return self::resolve($path);
    }

    protected static function resolve(?string $path): ?string
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
