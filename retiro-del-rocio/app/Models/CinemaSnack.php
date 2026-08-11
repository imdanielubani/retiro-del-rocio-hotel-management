<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CinemaSnack extends Model
{
    protected $fillable = ['name', 'price', 'image', 'is_active', 'sort_order'];

    protected $casts = [
        'price' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }

    public function imageUrl(): string
    {
        return SiteContent::imageUrl($this->image ?: 'images/popcorn.png');
    }

    /** The payload the guest tablet's Cinema screen renders. */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (int) $this->price,
            'price_label' => 'NGN '.number_format((int) $this->price),
            'image_url' => $this->imageUrl(),
        ];
    }
}
