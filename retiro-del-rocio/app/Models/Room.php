<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Room extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'price', 'beds', 'guests', 'sqft', 'bathrooms',
        'short_description', 'description', 'cancellation_policy', 'amenities', 'additional',
        'featured_image', 'gallery', 'is_published', 'sort_order',
    ];

    protected $casts = [
        'amenities' => 'array',
        'additional' => 'array',
        'gallery' => 'array',
        'is_published' => 'boolean',
        'price' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function units()
    {
        return $this->hasMany(RoomUnit::class)->orderByRaw('LENGTH(number), number');
    }

    /**
     * How many room numbers are free to book.
     * Returns null when no room numbers are configured (no inventory limit).
     */
    public function availableUnitsCount(): ?int
    {
        $total = $this->units()->count();
        if ($total === 0) {
            return null;
        }

        return $this->units()->where('status', 'available')->count();
    }

    /** A room is fully booked when it has room numbers and none are available. */
    public function isFullyBooked(): bool
    {
        $available = $this->availableUnitsCount();

        return $available !== null && $available <= 0;
    }

    /**
     * How many room numbers are free for a given date range.
     * Returns null when no room numbers are configured (no inventory limit).
     * A stay occupies nights [check_in, check_out); the check-out day is free.
     */
    public function availableUnitsForDates($checkIn, $checkOut): ?int
    {
        $total = $this->units()->count();
        if ($total === 0) {
            return null;
        }

        $in = \Illuminate\Support\Carbon::parse($checkIn)->toDateString();
        $out = \Illuminate\Support\Carbon::parse($checkOut)->toDateString();

        $overlapping = Booking::query()
            ->where('room_id', $this->id)
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereDate('check_in', '<', $out)
            ->whereDate('check_out', '>', $in)
            ->count();

        return max(0, $total - $overlapping);
    }

    public function isAvailableForDates($checkIn, $checkOut): bool
    {
        $available = $this->availableUnitsForDates($checkIn, $checkOut);

        return $available === null || $available > 0;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Resolve a stored image path to a public URL.
     * - null            → null (caller shows a placeholder)
     * - "images/x.jpg"  → asset() (seeded files already in /public/images)
     * - "rooms/x.jpg"   → Storage public disk (admin uploads)
     */
    public function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'images/')) {
            return \App\Support\Webp::url(str_replace(' ', '%20', asset($path)));
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return \App\Support\Webp::url(Storage::disk('public')->url($path));
    }

    public function featuredUrl(): ?string
    {
        return $this->imageUrl($this->featured_image);
    }

    /** @return list<string> */
    public function galleryUrls(): array
    {
        $images = $this->gallery ?: [];

        if ($this->featured_image && ! in_array($this->featured_image, $images, true)) {
            array_unshift($images, $this->featured_image);
        }

        return array_values(array_filter(array_map(fn ($p) => $this->imageUrl($p), $images)));
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }
}
