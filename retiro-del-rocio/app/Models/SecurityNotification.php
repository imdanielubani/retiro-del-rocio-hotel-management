<?php

namespace App\Models;

use App\Events\SecurityNotificationSent;
use App\Http\Controllers\Api\V1\VisitorPassController;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A gate-relevant update pushed to security — feeds the security tablet's
 * Notifications screen. Security is one shared station (not a per-guest
 * device like a room tablet), so the feed is shared across whoever is signed
 * in rather than scoped to an officer or device.
 *
 * Today the only trigger is a guest inviting a visitor (see
 * {@see VisitorPassController::store()}) —
 * security needs to know a pass is coming before the visitor reaches the
 * gate. Category is kept as a free string (mirroring
 * {@see ReceptionNotification}) so a future trigger doesn't need a migration.
 */
class SecurityNotification extends Model
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
     * Create a security notification and push a live signal over the
     * hotel-wide `security` channel (see {@see SecurityNotificationSent}).
     * Broadcasting must never break the calling flow (a guest issuing a
     * visitor pass) — if the socket server is down the notification is still
     * recorded, and the tablet's own poll picks it up.
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
            broadcast(new SecurityNotificationSent);
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    /**
     * The payload the security tablet renders on the Notifications screen —
     * including which room and guest the notification is about, when it has
     * one, so the officer doesn't have to open the message to find out.
     */
    public function toSecurityArray(): array
    {
        $booking = $this->relationLoaded('booking') ? $this->booking : $this->booking()->first();

        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'suite_name' => $booking?->room_name,
            'room_number' => optional($booking?->roomUnit)->number,
            'guest_name' => $booking?->customer_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'read' => $this->read_at !== null,
        ];
    }
}
