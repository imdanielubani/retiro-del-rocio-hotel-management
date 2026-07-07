<?php

namespace App\Notifications;

use App\Models\RestaurantReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RestaurantReservationReceived extends Notification
{
    use Queueable;

    public function __construct(public RestaurantReservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->reservation;

        return [
            'restaurant_reservation_id' => $r->id,
            'code' => $r->code,
            'customer' => $r->customer_name ?: 'New reservation',
            'area' => $r->areaLabel(),
            'amount' => $r->feeLabel(),
            'title' => 'New restaurant reservation',
            'message' => trim($r->areaLabel().' · '.$r->guestsLabel().' · '.optional($r->reserved_date)->format('M j')),
            'url' => route('admin.restaurant.reservations'),
        ];
    }
}
