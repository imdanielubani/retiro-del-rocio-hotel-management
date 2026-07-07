<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TtlockAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $updated  True when this is a re-issued code after a date change.
     */
    public function __construct(public Booking $booking, public bool $updated = false) {}

    public function envelope(): Envelope
    {
        $prefix = $this->updated ? 'Updated Access Gate Pass — ' : 'Your Access Gate Pass — ';

        return new Envelope(subject: $prefix.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ttlock-access',
            with: ['booking' => $this->booking, 'updated' => $this->updated],
        );
    }
}
