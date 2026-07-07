<?php

namespace App\Mail;

use App\Models\GymMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GymMembershipSuspended extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GymMembership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your gym membership access has been suspended — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gym-suspended',
            with: ['membership' => $this->membership],
        );
    }
}
