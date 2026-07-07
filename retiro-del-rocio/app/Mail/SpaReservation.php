<?php

namespace App\Mail;

use App\Models\SpaBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpaReservation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SpaBooking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your spa reservation is confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.spa-reservation',
            with: ['booking' => $this->booking],
        );
    }
}
