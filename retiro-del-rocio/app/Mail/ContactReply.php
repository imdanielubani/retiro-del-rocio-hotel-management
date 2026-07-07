<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contactMessage,
        public string $replyBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: your enquiry to Retiro Del Rocio',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-reply',
            text: 'emails.contact-reply-text',
            with: [
                // NOTE: `$message` is reserved inside mail views (the Mail\Message
                // instance), so the enquiry is passed as `$contact`.
                'contact' => $this->contactMessage,
                'reply' => $this->replyBody,
            ],
        );
    }
}
