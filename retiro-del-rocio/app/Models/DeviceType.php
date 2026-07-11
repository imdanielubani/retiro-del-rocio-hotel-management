<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeviceType extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'status', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeviceType $type) {
            if (empty($type->slug)) {
                $type->slug = static::uniqueSlug($type->name);
            }
        });
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'type';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
