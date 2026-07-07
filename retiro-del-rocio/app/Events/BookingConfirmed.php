<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired when a booking enters the paid/confirmed state. */
class BookingConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public Booking $booking) {}
}
