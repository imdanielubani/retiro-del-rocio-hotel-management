<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingReceived extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * Stored in the database for the admin bell.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $b = $this->booking;
        $hasPickup = $b->isPickup();

        return [
            'booking_id' => $b->id,
            'code' => $b->bookingCode(),
            'customer' => $b->customer_name ?: 'New guest',
            'room' => $b->room_name,
            'amount' => $b->amountLabel(),
            'has_pickup' => $hasPickup,
            'vehicle' => $hasPickup ? $b->pickup_vehicle : null,
            'title' => 'New booking',
            'message' => trim(($b->room_name ?: 'Reservation').' · '.$b->amountLabel())
                .($hasPickup ? ' · '.$b->pickup_vehicle.' pickup' : ''),
            'url' => route('admin.bookings.show', $b->id),
        ];
    }
}
