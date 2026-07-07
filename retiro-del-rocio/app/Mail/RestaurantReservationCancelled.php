<?php

namespace App\Mail;

use App\Models\RestaurantReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestaurantReservationCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RestaurantReservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your reservation has been cancelled — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.restaurant-cancelled',
            with: ['reservation' => $this->reservation],
        );
    }
}
