<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A named group of smart-device actions ("Welcome", "Relax", "Sleep") a
 * guest can trigger in one tap. Exactly one of `room_id` (a category-level
 * template, e.g. every Deluxe Room) or `room_unit_id` (a room-specific
 * override/addition) is set — a room-level scene takes precedence over a
 * same-slug category template. See docs/architecture/02-smart-room-architecture.md.
 */
class SmartScene extends Model
{
    protected $fillable = [
        'room_id', 'room_unit_id', 'name', 'slug', 'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SmartScene $scene) {
            if (filled($scene->room_id) === filled($scene->room_unit_id)) {
                throw ValidationException::withMessages([
                    'room_id' => ['A scene must belong to exactly one of a room category or a specific room.'],
                ]);
            }
        });
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function actions()
    {
        return $this->hasMany(SmartSceneAction::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scenes applicable to a given room unit: its own room-specific scenes
     * plus its room category's templates, room-level taking precedence over
     * a same-slug category template.
     */
    public function scopeForRoomUnit(Builder $query, RoomUnit $unit): Builder
    {
        return $query->where(function (Builder $q) use ($unit) {
            $q->where('room_unit_id', $unit->id)
                ->orWhere('room_id', $unit->room_id);
        });
    }
}
