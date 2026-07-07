<?php

namespace App\Notifications;

use App\Models\CinemaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CinemaBookingReceived extends Notification
{
    use Queueable;

    public function __construct(public CinemaBooking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $b = $this->booking;

        return [
            'cinema_booking_id' => $b->id,
            'code' => $b->code,
            'customer' => $b->customer_name ?: 'New booking',
            'movie' => $b->movie_title,
            'amount' => $b->amountLabel(),
            'title' => 'New cinema booking',
            'message' => trim($b->movie_title.' · '.$b->roomLabel().' · '.$b->amountLabel()),
            'url' => route('admin.cinema.bookings'),
        ];
    }
}
