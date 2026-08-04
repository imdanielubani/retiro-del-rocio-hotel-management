<?php

namespace App\Models;

use App\Events\MaintenanceNotificationSent;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A work-order-relevant update pushed to maintenance — feeds the maintenance
 * tablet's Notifications screen. Maintenance is one shared station, so the
 * feed (and its read state) is shared across whoever is signed in, same as
 * housekeeping's.
 *
 * Triggers: a new work order lands (`new_work_order`), or one is urgent
 * (`urgent_work_order`) — see {@see WorkOrder}'s creation paths.
 */
class MaintenanceNotification extends Model
{
    protected $fillable = [
        'work_order_id', 'category', 'title', 'message', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Create a maintenance notification and push a live signal over the
     * hotel-wide `maintenance` channel. Broadcasting must never break the
     * calling flow — if the socket server is down the notification is still
     * recorded, and the tablet's own poll picks it up.
     */
    public static function notify(string $category, string $title, string $message, ?WorkOrder $workOrder = null): self
    {
        $notification = static::create([
            'work_order_id' => $workOrder?->id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
        ]);

        try {
            broadcast(new MaintenanceNotificationSent);
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    public function toMaintenanceArray(): array
    {
        $order = $this->relationLoaded('workOrder') ? $this->workOrder : $this->workOrder()->first();

        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'work_order_id' => $this->work_order_id,
            'location_label' => $order?->locationLabel(),
            'created_at' => $this->created_at?->toIso8601String(),
            'read' => $this->read_at !== null,
        ];
    }
}
