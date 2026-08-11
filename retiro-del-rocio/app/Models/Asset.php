<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A registered hotel asset (equipment/fixture) maintenance tracks service
 * history and a preventive-maintenance schedule for — e.g. a room's AC unit,
 * the lobby generator, a lift.
 */
class Asset extends Model
{
    protected $fillable = [
        'name', 'category', 'room_unit_id', 'location_label', 'notes',
        'service_interval_days', 'last_serviced_at',
    ];

    protected $casts = [
        'last_serviced_at' => 'datetime',
        'service_interval_days' => 'integer',
    ];

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** Where this asset lives — its room, or its free-text location. */
    public function locationLabel(): string
    {
        $unit = $this->relationLoaded('roomUnit') ? $this->roomUnit : $this->roomUnit()->first();

        if ($unit?->number) {
            return 'Room '.$unit->number;
        }

        return $this->location_label ?: 'Hotel-wide';
    }

    /** No schedule tracked, or the interval hasn't elapsed since the last service (or creation, if never serviced). */
    public function isDueForService(): bool
    {
        if (! $this->service_interval_days) {
            return false;
        }

        $since = $this->last_serviced_at ?? $this->created_at;

        return $since !== null && $since->addDays($this->service_interval_days)->isPast();
    }

    /** null when not on a PM schedule. */
    public function nextServiceDueAt(): ?Carbon
    {
        if (! $this->service_interval_days) {
            return null;
        }

        $since = $this->last_serviced_at ?? $this->created_at;

        return $since?->copy()->addDays($this->service_interval_days);
    }

    public function toMaintenanceArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'location_label' => $this->locationLabel(),
            'notes' => $this->notes,
            'service_interval_days' => $this->service_interval_days,
            'last_serviced_at' => $this->last_serviced_at?->toIso8601String(),
            'last_serviced_label' => $this->last_serviced_at?->diffForHumans(),
            'next_service_due_at' => $this->nextServiceDueAt()?->toIso8601String(),
            'is_due_for_service' => $this->isDueForService(),
        ];
    }

    /** The Assets screen's service-history list for this asset — every work order ever raised against it, newest first. */
    public function toMaintenanceDetailArray(): array
    {
        $history = $this->relationLoaded('workOrders')
            ? $this->workOrders
            : $this->workOrders()->latest('id')->limit(50)->get();

        return [
            ...$this->toMaintenanceArray(),
            'service_history' => $history->sortByDesc('id')->values()->map->toMaintenanceArray()->values(),
        ];
    }
}
