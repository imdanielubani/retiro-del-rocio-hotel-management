<?php

namespace App\Mail;

use App\Models\VisitorPass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitorPassMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisitorPass $pass) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Visitor Pass — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visitor-pass',
            with: ['pass' => $this->pass],
        );
    }
}
