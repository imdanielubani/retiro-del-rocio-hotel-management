<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An item a housekeeper found while turning over a room (or a common area),
 * from first logged through to being handed back or disposed of:
 * unclaimed → returned | disposed.
 */
class LostFoundItem extends Model
{
    public const UNCLAIMED = 'unclaimed';

    public const RETURNED = 'returned';

    public const DISPOSED = 'disposed';

    public const STATUSES = [self::UNCLAIMED, self::RETURNED, self::DISPOSED];

    // Matches the migration's column default. Without this, a freshly
    // `create()`d instance has a null `status` in memory until re-fetched —
    // `markReturned()`/`markDisposed()` would then see null !== UNCLAIMED and
    // silently no-op.
    protected $attributes = [
        'status' => self::UNCLAIMED,
    ];

    protected $fillable = [
        'room_unit_id', 'booking_id', 'item_description', 'notes', 'found_by', 'found_at',
        'status', 'claimant_name', 'claimant_contact', 'returned_at', 'returned_by',
    ];

    protected $casts = [
        'found_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function foundBy()
    {
        return $this->belongsTo(User::class, 'found_by');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isUnclaimed(): bool
    {
        return $this->status === self::UNCLAIMED;
    }

    /** Hand the item back — unclaimed → returned. Idempotent. */
    public function markReturned(?User $officer = null, ?string $claimantName = null, ?string $claimantContact = null): bool
    {
        if (! $this->isUnclaimed()) {
            return false;
        }

        return $this->update([
            'status' => self::RETURNED,
            'claimant_name' => $claimantName ?: $this->claimant_name,
            'claimant_contact' => $claimantContact ?: $this->claimant_contact,
            'returned_at' => now(),
            'returned_by' => $officer?->id,
        ]);
    }

    /** Never claimed, discarded per policy — unclaimed → disposed. Idempotent. */
    public function markDisposed(?User $officer = null): bool
    {
        if (! $this->isUnclaimed()) {
            return false;
        }

        return $this->update([
            'status' => self::DISPOSED,
            'returned_at' => now(),
            'returned_by' => $officer?->id,
        ]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::RETURNED => 'Returned',
            self::DISPOSED => 'Disposed',
            default => 'Unclaimed',
        };
    }

    /** The payload the housekeeping tablet renders on the Lost & Found screen. */
    public function toHousekeepingArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $room = $unit?->relationLoaded('room') ? $unit->room : $unit?->room()->first();
        $founder = $this->relationLoaded('foundBy') ? $this->foundBy : $this->foundBy()->first();

        return [
            'id' => $this->id,
            'room_unit_id' => $this->room_unit_id,
            'room_number' => $unit?->number,
            'room_name' => $room?->name,
            'item_description' => $this->item_description,
            'notes' => $this->notes,
            'found_by_name' => $founder?->name,
            'found_at' => $this->found_at?->toIso8601String(),
            'found_label' => optional($this->found_at)->diffForHumans(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_unclaimed' => $this->isUnclaimed(),
            'claimant_name' => $this->claimant_name,
            'claimant_contact' => $this->claimant_contact,
        ];
    }
}
