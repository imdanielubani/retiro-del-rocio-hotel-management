<?php

namespace App\Notifications;

use App\Models\GymMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GymMembershipReceived extends Notification
{
    use Queueable;

    public function __construct(public GymMembership $membership) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->membership;

        return [
            'gym_membership_id' => $m->id,
            'code' => $m->code,
            'customer' => $m->customer_name ?: 'New member',
            'plan' => $m->plan_name,
            'amount' => $m->priceLabel(),
            'title' => 'New gym membership',
            'message' => trim($m->plan_name.' · '.$m->priceLabel().' · '.$m->typeLabel()),
            'url' => route('admin.gym.memberships'),
        ];
    }
}
