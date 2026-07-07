<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a booking's check-in/check-out dates change. */
class BookingDatesChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}
}
