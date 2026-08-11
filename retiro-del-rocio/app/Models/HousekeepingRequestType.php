<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A selectable housekeeping request type — what used to be a hardcoded PHP
 * constant is now an admin-managed catalog, so a new ask (e.g. "Extra
 * Pillow") can be added without a deploy and shows up on the guest tablet the
 * next time it fetches the list.
 */
class HousekeepingRequestType extends Model
{
    protected $fillable = [
        'key', 'label', 'icon', 'guest_visible', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'guest_visible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** The curated icon keys the guest tablet's icon map actually understands. */
    public const ICONS = [
        'dry_cleaning', 'soap', 'do_not_disturb_on', 'cleaning_services', 'more_horiz',
        'bed', 'iron', 'local_laundry_service', 'bathtub', 'water_drop',
        'coffee', 'checkroom', 'fact_check', 'room_service',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Types the guest's own Service Request screen may offer. */
    public function scopeGuestVisible($query)
    {
        return $query->where('guest_visible', true)->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /** The payload the guest tablet fetches to build its request-type tiles. */
    public function toGuestArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
        ];
    }

    /** The payload the admin catalog screen renders and edits. */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'guest_visible' => $this->guest_visible,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
