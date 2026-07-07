<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    protected $fillable = [
        'name', 'area', 'capacity', 'shape', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('capacity');
    }

    public function scopeArea($q, string $area)
    {
        return $q->where('area', $area);
    }

    public function reservations()
    {
        return $this->hasMany(RestaurantReservation::class);
    }

    public function areaLabel(): string
    {
        return $this->area === 'lounge' ? 'Lounge' : 'Dining';
    }

    public function capacityLabel(): string
    {
        return $this->capacity.' '.\Illuminate\Support\Str::plural('Guest', $this->capacity);
    }
}
