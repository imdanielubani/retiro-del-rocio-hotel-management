<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'price', 'seats', 'suitcases',
        'status', 'free_cancellation', 'image', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'seats' => 'integer',
        'suitcases' => 'integer',
        'free_cancellation' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeBookable($query)
    {
        // Only vehicles that are actually available are offered on the website.
        // In-use and maintenance vehicles are hidden from guests.
        return $query->where('status', 'available');
    }

    /* ---------------- Helpers ---------------- */

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'in_use' => 'In Use',
            'maintenance' => 'Maintenance',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'available' => '#16a34a',   // green
            'in_use' => '#7c3aed',      // purple
            'maintenance' => '#d97706', // amber
            default => '#6b7280',
        };
    }

    /**
     * Completed trips for this vehicle, derived from website bookings that
     * selected it for airport pick-up.
     */
    public function tripsCount(): int
    {
        return Booking::query()
            ->where('pickup_vehicle', $this->name)
            ->whereIn('status', ['paid', 'checked_in', 'checked_out'])
            ->count();
    }

    public function imageUrl(): ?string
    {
        $path = $this->image;
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
}
