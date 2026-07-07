<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactEnquiry extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{first_name:string,last_name:string,email:string,phone:?string,message:?string}  $data
     */
    public function __construct(public array $data)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New contact enquiry from '.$this->data['first_name'].' '.$this->data['last_name'],
            replyTo: [new Address($this->data['email'], $this->data['first_name'].' '.$this->data['last_name'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-enquiry',
            with: ['data' => $this->data],
        );
    }
}
