<?php

namespace App\Models;

use App\Events\RoomStatusChanged;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class RoomUnit extends Model
{
    protected $fillable = [
        'room_id', 'number', 'status', 'booking_id', 'lock_id', 'lock_alias',
        'housekeeping_status', 'housekeeping_status_at',
    ];

    protected $casts = [
        'housekeeping_status_at' => 'datetime',
    ];

    public const HOUSEKEEPING_STATUSES = ['clean', 'dirty', 'preparing', 'inspected', 'out_of_order'];

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
            if (! $unit->wasChanged('status')) {
                return;
            }

            // Realtime is an accelerator, never a dependency. If the broadcaster
            // is down or unreachable the guest is still checked in — the tablet
            // just falls back to its 20-second poll. Reception must never see a
            // 500 with a guest standing at the desk.
            try {
                broadcast(RoomStatusChanged::forUnit($unit));
            } catch (Throwable $e) {
                report($e);
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

    /** In-room Tuya smart devices (lights/AC/curtains/TV) assigned to this room number. */
    public function smartDevices()
    {
        return $this->hasMany(SmartDevice::class);
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available');
    }

    public function scopeNeedsAttention($q)
    {
        return $q->whereIn('housekeeping_status', ['dirty', 'preparing', 'out_of_order']);
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

    /** Guest requests raised for this room (towels, amenities, DND, …). */
    public function housekeepingRequests()
    {
        return $this->hasMany(HousekeepingRequest::class);
    }

    /** Faults reported against this room. */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function housekeepingStatusLabel(): string
    {
        return match ($this->housekeeping_status) {
            'dirty' => 'Dirty',
            'preparing' => 'Preparing',
            'inspected' => 'Inspected',
            'out_of_order' => 'Out of Order',
            default => 'Clean',
        };
    }

    /** [text, background] hex for the housekeeping status pill. */
    public function housekeepingStatusColors(): array
    {
        return match ($this->housekeeping_status) {
            'dirty' => ['#d97706', '#fef3c7'],
            'preparing' => ['#2563eb', '#dbeafe'],
            'inspected' => ['#2563eb', '#dbeafe'],
            'out_of_order' => ['#dc2626', '#fee2e2'],
            default => ['#16a34a', '#dcfce7'],
        };
    }

    /**
     * The payload the housekeeping tablet renders for the room grid and the
     * room detail screen — this room's occupancy and cleanliness, whether
     * the guest currently in it is checking out today (a room due to turn
     * over is more urgent than one merely due a mid-stay tidy), and whether
     * reception has asked for a pre-checkout inspection.
     *
     * [$needsInspection] is looked up in bulk by the caller — whether the
     * room's current booking has an open checkout-inspection request — to
     * avoid an N+1 when rendering the whole grid.
     */
    public function toHousekeepingRoomArray(bool $needsInspection = false): array
    {
        $room = $this->relationLoaded('room') ? $this->room : $this->room()->first();
        $booking = $this->relationLoaded('booking') ? $this->booking : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'room_name' => $room?->name,
            'occupancy' => $this->status,
            'occupancy_label' => $this->statusLabel(),
            'housekeeping_status' => $this->housekeeping_status,
            'housekeeping_status_label' => $this->housekeepingStatusLabel(),
            'guest_name' => $booking?->customer_name,
            'checkout_today' => $booking && $booking->status === 'checked_in'
                && optional($booking->check_out)->isToday(),
            'needs_inspection' => $needsInspection,
            'updated_label' => optional($this->housekeeping_status_at)->diffForHumans(),
        ];
    }

    /**
     * The payload the reception tablet renders on the read-only Room Status
     * board — a hotel-wide view combining this room's occupancy (from its
     * booking), housekeeping's cleanliness flag, and whether maintenance has
     * an open fault against it. Reception watches this, it doesn't change it.
     *
     * [$hasOpenWorkOrder] is looked up in bulk by the caller across every
     * room in one query, rather than per-room here, to avoid an N+1 when
     * rendering the whole board.
     */
    public function toReceptionRoomArray(bool $hasOpenWorkOrder): array
    {
        $room = $this->relationLoaded('room') ? $this->room : $this->room()->first();
        $booking = $this->relationLoaded('booking') ? $this->booking : null;

        $isOccupied = $this->status === 'occupied';
        $isMaintenance = $this->status === 'maintenance'
            || $this->housekeeping_status === 'out_of_order'
            || $hasOpenWorkOrder;
        // Checking a guest out marks the room "dirty" automatically, so
        // "preparing" only ever reflects housekeeping's own sign-off that
        // they've started the turnover clean — a real status, not a guess.
        $isPreparing = ! $isOccupied && ! $isMaintenance && $this->housekeeping_status === 'preparing';
        $isDirty = ! $isOccupied && ! $isMaintenance && $this->housekeeping_status === 'dirty';

        $boardStatus = match (true) {
            $isOccupied => 'occupied',
            $isMaintenance => 'maintenance',
            $isPreparing => 'preparing',
            $isDirty => 'dirty',
            default => 'ready',
        };

        return [
            'id' => $this->id,
            'number' => $this->number,
            'room_name' => $room?->name,
            'occupancy' => $this->status,
            'occupancy_label' => $this->statusLabel(),
            'housekeeping_status' => $this->housekeeping_status,
            'housekeeping_status_label' => $this->housekeepingStatusLabel(),
            'guest_name' => $booking?->customer_name,
            'has_open_work_order' => $hasOpenWorkOrder,
            'board_status' => $boardStatus,
            'board_status_label' => match ($boardStatus) {
                'occupied' => 'Occupied',
                'maintenance' => 'Maintenance',
                'preparing' => 'Preparing',
                'dirty' => 'Dirty',
                default => 'Ready',
            },
            'updated_label' => optional($this->housekeeping_status_at)->diffForHumans(),
        ];
    }
}
