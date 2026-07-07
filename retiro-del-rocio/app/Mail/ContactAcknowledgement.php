<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactAcknowledgement extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{first_name:string,last_name:string,email:string,phone:?string,message:?string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank you for contacting Retiro Del Rocio',
            replyTo: [new Address(config('mail.contact_to', config('mail.from.address')), 'Retiro Del Rocio Support')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-acknowledgement',
            text: 'emails.contact-acknowledgement-text',
            with: ['data' => $this->data],
        );
    }
}
