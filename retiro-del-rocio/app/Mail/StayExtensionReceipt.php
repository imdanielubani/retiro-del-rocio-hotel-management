<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\StayExtensionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The receipt a guest gets after paying to extend their stay from the in-room
 * tablet — confirms the charge and the new checkout date.
 */
class StayExtensionReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public StayExtensionPayment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your stay extension is confirmed — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        // Subtotal is the room charge; VAT is a separate tax line paid at checkout.
        $subtotal = max(0, (int) $this->payment->amount - (int) $this->payment->vat);

        return new Content(
            view: 'emails.stay-extension-receipt',
            with: [
                'booking' => $this->booking,
                'payment' => $this->payment,
                'subtotal' => $subtotal,
            ],
        );
    }
}
