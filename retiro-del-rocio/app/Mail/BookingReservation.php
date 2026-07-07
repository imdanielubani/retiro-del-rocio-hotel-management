<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReservation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your reservation is confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-reservation',
            text: 'emails.booking-reservation-text',
            with: ['booking' => $this->booking],
        );
    }
}
