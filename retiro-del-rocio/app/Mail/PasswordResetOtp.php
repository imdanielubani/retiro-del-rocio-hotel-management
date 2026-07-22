<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public int $ttlMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your password reset code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'ttl' => $this->ttlMinutes,
            ],
        );
    }
}
