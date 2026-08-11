<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaCategory extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function services()
    {
        return $this->hasMany(SpaService::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** The payload the guest tablet's Spa & Wellness screen renders. */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'color' => $this->color,
        ];
    }
}
