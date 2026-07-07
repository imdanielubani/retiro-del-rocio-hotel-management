<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $data
     */
    public function __construct(public array $data)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New reservation request — '.$this->data['room'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-request',
            with: ['data' => $this->data],
        );
    }
}
