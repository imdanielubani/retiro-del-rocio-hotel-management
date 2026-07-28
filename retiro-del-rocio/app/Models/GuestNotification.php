<?php

namespace App\Models;

use App\Events\GuestNotificationSent;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A hotel update pushed to a guest's in-room tablet — feeds the Notifications
 * screen. Scoped to one booking, so a new guest checking into the same room
 * never sees the previous occupant's notifications.
 */
class GuestNotification extends Model
{
    protected $fillable = [
        'booking_id', 'room_unit_id', 'category', 'title', 'message', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    /**
     * Create a notification for a guest's active stay and push a live signal to
     * their tablet over the room's existing realtime channel (see
     * {@see GuestNotificationSent}). Broadcasting must never break the calling
     * flow (e.g. a stay extension succeeding) — if the socket server is down the
     * notification is still recorded, and the tablet's own poll picks it up.
     */
    public static function notify(
        Booking $booking,
        RoomUnit $unit,
        string $category,
        string $title,
        string $message,
    ): self {
        $notification = static::create([
            'booking_id' => $booking->id,
            'room_unit_id' => $unit->id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
        ]);

        try {
            broadcast(new GuestNotificationSent($unit->id));
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    /** The payload the guest tablet renders on the Notifications screen. */
    public function toGuestArray(): array
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
