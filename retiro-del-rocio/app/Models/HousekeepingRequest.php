<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\GuestServiceRequestController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A guest-facing housekeeping ask — towels, amenities, Do Not Disturb, or a
 * make-up-room visit — for housekeeping to see and clear.
 */
class HousekeepingRequest extends Model
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    /**
     * Raised by reception, not the guest — a pre-checkout ask for
     * housekeeping to inspect an occupied room before the guest is allowed
     * to leave. Everything else in this model (the pending/completed
     * lifecycle, the Requests queue, the notification on create) is shared
     * with the guest-raised types; only the guest-facing history excludes it
     * (see {@see GuestServiceRequestController}).
     */
    public const CHECKOUT_INSPECTION = 'checkout_inspection';

    public const TYPES = ['towels', 'amenities', 'dnd', 'make_up_room', 'other', self::CHECKOUT_INSPECTION];

    /** Cache keys {@see typeLabel()}, {@see activeTypeKeys()} and {@see guestVisibleTypeKeys()} share. */
    public const TYPE_CATALOG_CACHE_KEY = 'housekeeping_request_types.catalog';

    public const GUEST_VISIBLE_TYPE_CACHE_KEY = 'housekeeping_request_types.guest_visible';

    /** Called by the admin catalog after any create/update/delete, so an edit is felt within the request. */
    public static function flushTypeCatalogCache(): void
    {
        Cache::forget(self::TYPE_CATALOG_CACHE_KEY);
        Cache::forget(self::GUEST_VISIBLE_TYPE_CACHE_KEY);
    }

    /**
     * Every currently valid type key, backed by the admin-managed
     * {@see HousekeepingRequestType} catalog so a new ask can be added
     * without a deploy. Falls back to the original hardcoded {@see TYPES}
     * set if the catalog is ever empty, so validation can never hard-fail
     * because of it.
     */
    public static function activeTypeKeys(): array
    {
        $keys = array_keys(self::typeCatalog());

        return $keys ?: self::TYPES;
    }

    /**
     * Only the types the guest's own Service Request screen may offer — the
     * reception-raised checkout inspection is deliberately excluded here (not
     * just from the guest's history), so a guest can never POST that type
     * themselves and short-circuit reception's own inspection gate.
     */
    public static function guestVisibleTypeKeys(): array
    {
        $keys = Cache::remember(
            self::GUEST_VISIBLE_TYPE_CACHE_KEY,
            60,
            fn () => HousekeepingRequestType::guestVisible()->pluck('key')->all(),
        );

        return $keys ?: array_diff(self::TYPES, [self::CHECKOUT_INSPECTION]);
    }

    /** [key => label] for every active catalog entry, cached briefly — see {@see typeLabel()}. */
    protected static function typeCatalog(): array
    {
        return Cache::remember(
            self::TYPE_CATALOG_CACHE_KEY,
            60,
            fn () => HousekeepingRequestType::active()->pluck('label', 'key')->all(),
        );
    }

    // Matches the migration's column default. Without this, a freshly
    // `create()`d instance has a null `status` in memory until re-fetched —
    // `complete()` would then see null !== PENDING and silently no-op.
    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected $fillable = [
        'room_unit_id', 'booking_id', 'type', 'notes', 'status', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Mark the request as cleared. Idempotent — a second call is a no-op. */
    public function complete(?User $officer = null): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->update([
            'status' => self::COMPLETED,
            'completed_by' => $officer?->id,
            'completed_at' => now(),
        ]);
    }

    public function typeLabel(): string
    {
        return self::typeCatalog()[$this->type] ?? match ($this->type) {
            'towels' => 'Towels',
            'amenities' => 'Amenities',
            'dnd' => 'Do Not Disturb',
            'make_up_room' => 'Make Up Room',
            self::CHECKOUT_INSPECTION => 'Checkout Inspection',
            default => 'Other',
        };
    }

    /** The payload the housekeeping tablet renders on the Requests screen. */
    public function toHousekeepingArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $room = $unit?->relationLoaded('room') ? $unit->room : $unit?->room()->first();
        $booking = $this->relationLoaded('booking') ? $this->booking : $this->booking()->first();

        return [
            'id' => $this->id,
            'room_unit_id' => $this->room_unit_id,
            'room_number' => $unit?->number,
            'room_name' => $room?->name,
            'guest_name' => $booking?->customer_name,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'notes' => $this->notes,
            'status' => $this->status,
            'is_pending' => $this->isPending(),
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }

    /**
     * A compact alert row for the reception dashboard's "Alerts" panel,
     * mirroring SosAlert::toReceptionAlertArray() — a guest's housekeeping
     * ask is pushed to the desk the moment it lands, not left buried in the
     * separate notifications inbox.
     */
    public function toReceptionAlertArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $room = $unit?->number ? 'Room '.$unit->number : 'the hotel';

        return [
            'id' => $this->id,
            'type' => 'housekeeping_request',
            'title' => 'Housekeeping Request — '.$this->typeLabel().' ('.$room.')',
            'time_label' => optional($this->created_at)->diffForHumans(['short' => true]) ?? '',
            'severity' => 'medium',
        ];
    }

    /**
     * The payload the guest tablet renders on the Service Request screen's
     * history — the same request, from the guest's side, alongside their
     * maintenance faults in one combined feed (see
     * {@see GuestServiceRequestController}).
     */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'category' => 'housekeeping',
            'title' => $this->typeLabel(),
            'detail' => $this->notes,
            'status' => $this->status,
            'status_label' => $this->isPending() ? 'Pending' : 'Completed',
            'is_open' => $this->isPending(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }
}
