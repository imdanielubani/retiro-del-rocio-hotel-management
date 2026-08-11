<?php

namespace App\Models;

use App\Events\HousekeepingNotificationSent;
use App\Http\Controllers\Api\V1\GuestServiceRequestController;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A guest-relevant update pushed to housekeeping — feeds the housekeeping
 * tablet's Notifications screen. Housekeeping is one shared station (not a
 * per-guest device like a room tablet), so the feed is shared across
 * whoever is signed in rather than scoped to a housekeeper or device.
 *
 * Today the only trigger is a guest raising a housekeeping request (see
 * {@see GuestServiceRequestController::store()})
 * — housekeeping needs to know an ask is waiting before it turns up on the
 * next poll of the Requests screen.
 */
class HousekeepingNotification extends Model
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
     * Create a housekeeping notification and push a live signal over the
     * hotel-wide `housekeeping` channel (see {@see HousekeepingNotificationSent}).
     * Broadcasting must never break the calling flow — if the socket server
     * is down the notification is still recorded, and the tablet's own poll
     * picks it up.
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
            broadcast(new HousekeepingNotificationSent);
        } catch (Throwable $e) {
            report($e);
        }

        return $notification;
    }

    /**
     * The payload the housekeeping tablet renders on the Notifications
     * screen — including which room and guest the notification is about,
     * when it has one, so the housekeeper doesn't have to open the message
     * to find out.
     */
    public function toHousekeepingArray(): array
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
