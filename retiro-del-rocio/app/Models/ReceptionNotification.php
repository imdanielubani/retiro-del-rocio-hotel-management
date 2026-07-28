<?php

namespace App\Models;

use App\Events\ReceptionNotificationSent;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A hotel-wide update pushed to the front desk — feeds the reception
 * Notifications screen. Reception is one shared station (not a per-guest
 * device like a room tablet), so the feed is shared across whoever is signed
 * in rather than scoped to a receptionist or device.
 */
class ReceptionNotification extends Model
{
    protected $fillable = [
        'booking_id', 'category', 'title', 'message', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Create a front-desk notification and push a live signal over the
     * hotel-wide `reception` channel (see {@see ReceptionNotificationSent}).
     * Broadcasting must never break the calling flow (e.g. a stay-extension
     * payment succeeding) — if the socket server is down the notification is
     * still recorded, and the tablet's own poll picks it up.
     */
    public static function notify(
        string $category,
        string $title,
        string $message,
        ?Booking $booking = null,
    ): self {
        $notification = static::create([
            'booking_id' => $booking?->id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
        ]);

        try {
            broadcast(new ReceptionNotificationSent);
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    /** The payload the reception tablet renders on the Notifications screen. */
    public function toReceptionArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
            'read' => $this->read_at !== null,
        ];
    }
}
