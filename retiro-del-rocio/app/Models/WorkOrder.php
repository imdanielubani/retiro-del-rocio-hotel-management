<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\GuestServiceRequestController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    /** Hours to resolution before an open order is considered SLA-breached, by priority. */
    public const SLA_HOURS = ['urgent' => 2, 'high' => 8, 'medium' => 24, 'low' => 72];

    // Matches the migration's column defaults. Without this, a freshly
    // `create()`d instance has null `status`/`priority` in memory until
    // re-fetched — `accept()`/`start()` would then see null !== NEW and
    // silently no-op.
    protected $attributes = [
        'status' => self::NEW,
        'priority' => 'medium',
    ];

    protected $fillable = [
        'room_unit_id', 'booking_id', 'asset_label', 'asset_id', 'title', 'description', 'priority', 'status',
        'reported_by', 'assigned_to', 'accepted_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * A single hook for every creation path (the maintenance tablet's own
     * "Report Fault", a guest's Service Request, a housekeeper's fault
     * report) so the notification always fires no matter which screen raised
     * the order — no scattering `MaintenanceNotification::notify()` calls
     * across three different controllers where one could be missed.
     */
    protected static function booted(): void
    {
        static::created(function (self $order) {
            $urgent = $order->priority === 'urgent';

            MaintenanceNotification::notify(
                $urgent ? 'urgent_work_order' : 'new_work_order',
                ($urgent ? 'Urgent Work Order — ' : 'New Work Order — ').$order->title,
                $order->title.' ('.$order->locationLabel().')',
                $order,
            );
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function attachments()
    {
        return $this->hasMany(WorkOrderAttachment::class)->latest('id');
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

    /** Bump the order up one priority level (e.g. a technician flags it worse than first reported). No-op once already urgent. */
    public function escalate(): bool
    {
        $levels = self::PRIORITIES; // low, medium, high, urgent
        $index = array_search($this->priority, $levels, true);
        if ($index === false || $index >= count($levels) - 1) {
            return false;
        }

        return $this->update(['priority' => $levels[$index + 1]]);
    }

    /**
     * A manual override for the desk to jump the order straight to a given
     * status — the accept/start/complete chain covers the common path, but a
     * technician sometimes needs to correct a mis-tap without walking back
     * through every step.
     */
    public function setStatus(string $status): bool
    {
        if (! in_array($status, [self::NEW, self::ACCEPTED, self::IN_PROGRESS, self::DONE], true)) {
            return false;
        }

        $timestamps = match ($status) {
            self::ACCEPTED => ['accepted_at' => $this->accepted_at ?? now()],
            self::IN_PROGRESS => ['started_at' => $this->started_at ?? now()],
            self::DONE => ['completed_at' => $this->completed_at ?? now()],
            default => [],
        };

        return $this->update(['status' => $status, ...$timestamps]);
    }

    /** The deadline an open order needs resolving by, per its priority's SLA. */
    public function slaDueAt(): Carbon
    {
        return $this->created_at->copy()->addHours(self::SLA_HOURS[$this->priority] ?? self::SLA_HOURS['medium']);
    }

    /** Still open, and past its priority's SLA deadline. */
    public function isSlaBreached(): bool
    {
        return $this->isOpen() && $this->slaDueAt()->isPast();
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

    /** Where this order points at — a room, a named asset, or hotel-wide. */
    public function locationLabel(): string
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        if ($unit?->number) {
            return 'Room '.$unit->number;
        }

        $asset = $this->relationLoaded('asset') ? $this->asset : $this->asset()->first();

        return $asset?->name ?? $this->asset_label ?: 'Hotel-wide';
    }

    /** The payload the maintenance tablet renders on the Work Orders screen. */
    public function toMaintenanceArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $technician = $this->relationLoaded('assignedTo') ? $this->assignedTo : $this->assignedTo()->first();
        $asset = $this->relationLoaded('asset') ? $this->asset : $this->asset()->first();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'room_number' => $unit?->number,
            'asset_id' => $this->asset_id,
            'asset_label' => $asset?->name ?? $this->asset_label,
            'asset_category' => $asset?->category,
            'location_label' => $this->locationLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->priorityLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'reported_by' => $this->reported_by,
            'assigned_to_name' => $technician?->name,
            'created_label' => optional($this->created_at)->diffForHumans(),
            'sla_breached' => $this->isSlaBreached(),
        ];
    }

    /** The Work Order detail screen: everything in {@see toMaintenanceArray()} plus its attachments. */
    public function toMaintenanceDetailArray(): array
    {
        $attachments = $this->relationLoaded('attachments') ? $this->attachments : $this->attachments()->get();

        return [
            ...$this->toMaintenanceArray(),
            'attachments' => $attachments->map->toMaintenanceArray()->values(),
        ];
    }

    /**
     * A compact alert row for the reception dashboard's "Alerts" panel,
     * mirroring SosAlert::toReceptionAlertArray() — a guest-reported fault is
     * pushed to the desk the moment it lands, not left buried in the separate
     * notifications inbox. Severity follows the fault's own priority, so an
     * urgent fault reads the same way an SOS incident does.
     */
    public function toReceptionAlertArray(): array
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();
        $room = $unit?->number ? 'Room '.$unit->number : ($this->asset_label ?: 'the hotel');

        return [
            'id' => $this->id,
            'type' => 'maintenance_request',
            'title' => 'Maintenance Request — '.$this->title.' ('.$room.')',
            'time_label' => optional($this->created_at)->diffForHumans(['short' => true]) ?? '',
            'severity' => in_array($this->priority, ['urgent', 'high'], true) ? 'high' : 'medium',
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
