<?php

namespace App\Models;

use App\Events\KitchenNotificationSent;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A food-order-relevant update pushed to the kitchen — feeds the Kitchen
 * Tablet's new-order alert popup and notification feed. Kitchen is one
 * shared station, so the feed (and its read state) is shared across whoever
 * is signed in, same as bar's.
 *
 * Trigger: a new dining order containing a food item lands — see
 * {@see DiningOrder}'s `booted()` hook.
 */
class KitchenNotification extends Model
{
    protected $fillable = [
        'dining_order_id', 'category', 'title', 'message', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function diningOrder()
    {
        return $this->belongsTo(DiningOrder::class);
    }

    /**
     * Create a kitchen notification and push a live signal over the
     * hotel-wide `kitchen` channel. Broadcasting must never break the
     * calling flow — if the socket server is down the notification is
     * still recorded, and the tablet's own poll picks it up.
     */
    public static function notify(string $category, string $title, string $message, ?DiningOrder $order = null): self
    {
        $notification = static::create([
            'dining_order_id' => $order?->id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
        ]);

        try {
            broadcast(new KitchenNotificationSent);
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    public function toKitchenArray(): array
    {
        $order = $this->relationLoaded('diningOrder') ? $this->diningOrder : $this->diningOrder()->first();

        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'dining_order_id' => $this->dining_order_id,
            'order_code' => $order?->orderCode(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_label' => optional($this->created_at)->diffForHumans(),
            'read' => $this->read_at !== null,
        ];
    }
}
