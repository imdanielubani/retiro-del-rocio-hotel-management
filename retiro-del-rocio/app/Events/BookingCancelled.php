<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a booking is cancelled and its access should be revoked. */
class BookingCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}
}
