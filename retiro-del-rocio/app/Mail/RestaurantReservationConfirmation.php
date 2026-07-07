<?php

namespace App\Mail;

use App\Models\RestaurantReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestaurantReservationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantReservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your reservation is confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant-reservation',
            with: ['reservation' => $this->reservation],
        );
    }
}
