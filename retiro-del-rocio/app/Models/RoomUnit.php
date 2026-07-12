<?php

namespace App\Models;

use App\Events\RoomStatusChanged;
use Illuminate\Database\Eloquent\Model;

class RoomUnit extends Model
{
    protected $fillable = ['room_id', 'number', 'status', 'booking_id', 'lock_id', 'lock_alias'];

    /**
     * Whenever a room's occupancy changes — a check-in, a check-out, a
     * cancellation — tell the tablet bound to it, so it updates in the moment
     * instead of on its next 20-second poll.
     *
     * Hung off the model rather than each call site so no future code path can
     * change occupancy and silently forget to notify the room. Note this only
     * fires for model saves: a query-builder `->update()` bypasses it.
     */
    protected static function booted(): void
    {
        static::updated(function (self $unit) {
            if ($unit->wasChanged('status')) {
                broadcast(RoomStatusChanged::forUnit($unit));
            }
        });
    }

    /**
     * Free a room number, going through the model so the tablet bound to it is
     * notified. Pass [$onlyForBookingId] to release it only if that booking is
     * the one currently holding it.
     */
    public static function release(?int $unitId, ?int $onlyForBookingId = null): void
    {
        if (! $unitId || ! ($unit = static::find($unitId))) {
            return;
        }

        if ($onlyForBookingId !== null && $unit->booking_id !== $onlyForBookingId) {
            return;
        }

        $unit->update(['status' => 'available', 'booking_id' => null]);
    }

    public function hasLock(): bool
    {
        return filled($this->lock_id);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /** In-room tablets/TVs paired to this room number. */
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'occupied' => 'bg-[#fee2e2] text-[#dc2626]',
            'maintenance' => 'bg-[#fef3c7] text-[#d97706]',
            default => 'bg-[#dcfce7] text-[#16a34a]',
        };
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
