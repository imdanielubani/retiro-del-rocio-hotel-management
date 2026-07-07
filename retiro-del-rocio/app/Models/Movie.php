<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    protected $fillable = [
        'title', 'slug', 'genre', 'duration', 'rating', 'synopsis',
        'poster_image', 'backdrop_image', 'trailer_url',
        'adult_price', 'child_price', 'room_price', 'classification', 'showtimes',
        'is_featured', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'showtimes' => 'array',
        'adult_price' => 'integer',
        'child_price' => 'integer',
        'room_price' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The bookable private rooms per showtime, and how many seats each holds. */
    public const ROOMS = ['Room 1', 'Room 2'];

    public const SEATS_PER_ROOM = 4;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('title');
    }

    public function scopeNowShowing($q)
    {
        return $q->where('classification', 'now_showing');
    }

    public function scopeComingSoon($q)
    {
        return $q->where('classification', 'coming_soon');
    }

    public function bookings()
    {
        return $this->hasMany(CinemaBooking::class);
    }

    public function posterUrl(): string
    {
        return \App\Models\SiteContent::imageUrl($this->poster_image ?: 'images/image 5.jpg');
    }

    public function backdropUrl(): string
    {
        return \App\Models\SiteContent::imageUrl($this->backdrop_image ?: $this->poster_image ?: 'images/image 5.jpg');
    }

    /**
     * Absolute filesystem path to the poster, for embedding inline in emails
     * ($message->embed). Returns null for remote URLs or missing files.
     */
    public function posterPath(): ?string
    {
        $path = $this->poster_image ?: 'images/image 5.jpg';

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $abs = str_starts_with($path, 'images/')
            ? public_path($path)
            : storage_path('app/public/'.$path);

        return is_file($abs) ? $abs : null;
    }

    public function showtimeList(): array
    {
        return collect($this->showtimes ?? [])->filter()->values()->all();
    }

    public function adultPriceLabel(): string
    {
        return '₦'.number_format($this->adult_price);
    }

    public function childPriceLabel(): string
    {
        return '₦'.number_format($this->child_price);
    }

    public function roomPriceLabel(): string
    {
        return '₦'.number_format($this->room_price);
    }

    public function classificationLabel(): string
    {
        return $this->classification === 'coming_soon' ? 'Coming Soon' : 'Now Showing';
    }
}
