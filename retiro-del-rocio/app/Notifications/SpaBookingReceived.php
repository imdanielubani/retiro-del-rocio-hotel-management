<?php

namespace App\Notifications;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SpaBookingReceived extends Notification
{
    use Queueable;

    public function __construct(public SpaBooking $booking) {}

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

        return [
            'spa_booking_id' => $b->id,
            'code' => $b->sessionCode(),
            'customer' => $b->customer_name ?: 'New guest',
            'service' => $b->primaryService(),
            'amount' => $b->totalLabel(),
            'title' => 'New spa booking',
            'message' => trim($b->primaryService().' · '.$b->totalLabel()),
            'url' => route('admin.spa.bookings'),
        ];
    }
}
