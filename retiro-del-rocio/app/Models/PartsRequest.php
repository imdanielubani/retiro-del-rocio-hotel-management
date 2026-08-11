<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A technician's request for a part against a work order — the Requests tab's list. */
class PartsRequest extends Model
{
    public const PENDING = 'pending';

    public const FULFILLED = 'fulfilled';

    public const DENIED = 'denied';

    protected $attributes = [
        'status' => self::PENDING,
        'quantity' => 1,
    ];

    protected $fillable = [
        'work_order_id', 'part_name', 'quantity', 'note', 'status', 'requested_by', 'fulfilled_at',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Pending → fulfilled. Idempotent. */
    public function fulfill(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->update(['status' => self::FULFILLED, 'fulfilled_at' => now()]);
    }

    /** Pending → denied. Idempotent. */
    public function deny(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->update(['status' => self::DENIED]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::FULFILLED => 'Fulfilled',
            self::DENIED => 'Denied',
            default => 'Pending',
        };
    }

    public function toMaintenanceArray(): array
    {
        $order = $this->relationLoaded('workOrder') ? $this->workOrder : $this->workOrder()->first();

        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'work_order_title' => $order?->title,
            'location_label' => $order?->locationLabel(),
            'part_name' => $this->part_name,
            'quantity' => $this->quantity,
            'note' => $this->note,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'requested_by' => $this->requested_by,
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }
}
