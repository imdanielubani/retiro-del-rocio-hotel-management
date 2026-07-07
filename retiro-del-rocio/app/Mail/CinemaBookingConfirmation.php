<?php

namespace App\Mail;

use App\Models\CinemaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CinemaBookingConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CinemaBooking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your cinema tickets are confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cinema-booking',
            with: ['booking' => $this->booking],
        );
    }
}
