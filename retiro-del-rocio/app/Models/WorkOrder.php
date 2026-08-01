<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\GuestServiceRequestController;
use Illuminate\Database\Eloquent\Model;

/**
 * A maintenance fault, from report through to completion:
 * new → accepted → in_progress → done.
 */
class WorkOrder extends Model
{
    public const NEW = 'new';

    public const ACCEPTED = 'accepted';

    public const IN_PROGRESS = 'in_progress';

    public const DONE = 'done';

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    // Matches the migration's column defaults. Without this, a freshly
    // `create()`d instance has null `status`/`priority` in memory until
    // re-fetched — `accept()`/`start()` would then see null !== NEW and
    // silently no-op.
    protected $attributes = [
        'status' => self::NEW,
        'priority' => 'medium',
    ];

    protected $fillable = [
        'room_unit_id', 'booking_id', 'asset_label', 'title', 'description', 'priority', 'status',
        'reported_by', 'assigned_to', 'accepted_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOpen(): bool
    {
        return $this->status !== self::DONE;
    }

    /** Take the order — new → accepted. Idempotent. */
    public function accept(?User $technician = null): bool
    {
        if ($this->status !== self::NEW) {
            return false;
        }

        return $this->update([
            'status' => self::ACCEPTED,
            'assigned_to' => $technician?->id ?? $this->assigned_to,
            'accepted_at' => now(),
        ]);
    }

    /** Begin work — accepted → in_progress. Idempotent. */
    public function start(): bool
    {
        if ($this->status !== self::ACCEPTED) {
            return false;
        }

        return $this->update(['status' => self::IN_PROGRESS, 'started_at' => now()]);
    }

    /** Close the order — anything open → done. Idempotent. */
    public function complete(): bool
    {
        if ($this->status === self::DONE) {
            return false;
        }

        return $this->update(['status' => self::DONE, 'completed_at' => now()]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::ACCEPTED => 'Accepted',
            self::IN_PROGRESS => 'In Progress',
            self::DONE => 'Done',
            default => 'New',
        };
    }

    public function priorityLabel(): string
    {
        return ucfirst($this->priority);
    }

    /** [text, background] hex for the status pill. */
    public function statusColors(): array
    {
        return match ($this->status) {
            self::ACCEPTED => ['#2563eb', '#dbeafe'],
            self::IN_PROGRESS => ['#d97706', '#fef3c7'],
            self::DONE => ['#16a34a', '#dcfce7'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    /** [text, background] hex for the priority pill. */
    public function priorityColors(): array
    {
        return match ($this->priority) {
            'urgent' => ['#dc2626', '#fee2e2'],
            'high' => ['#d97706', '#fef3c7'],
            'low' => ['#6b7280', '#f3f4f6'],
            default => ['#2563eb', '#dbeafe'],
        };
    }

    /** The payload the maintenance tablet renders on the Work Orders screen. */
    public function toMaintenanceArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $technician = $this->relationLoaded('assignedTo') ? $this->assignedTo : $this->assignedTo()->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'room_number' => $unit?->number,
            'asset_label' => $this->asset_label,
            'location_label' => $unit?->number ? 'Room '.$unit->number : ($this->asset_label ?: 'Hotel-wide'),
            'priority' => $this->priority,
            'priority_label' => $this->priorityLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'reported_by' => $this->reported_by,
            'assigned_to_name' => $technician?->name,
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }

    /**
     * The payload the guest tablet renders on the Service Request screen's
     * history — the same fault, from the guest's side, alongside their
     * housekeeping asks in one combined feed (see
     * {@see GuestServiceRequestController}).
     */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'category' => 'maintenance',
            'title' => $this->title,
            'detail' => $this->description,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_open' => $this->isOpen(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }
}
