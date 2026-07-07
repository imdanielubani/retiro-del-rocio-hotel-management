<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MessageReceived extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $message) {}

    /**
     * Stored in the database for the admin bell.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->message;

        return [
            'message_id' => $m->id,
            'name' => $m->full_name ?: 'New enquiry',
            'initials' => $m->initials,
            'preview' => $m->message ?: 'New enquiry',
            'url' => route('admin.messages.index'),
        ];
    }
}
