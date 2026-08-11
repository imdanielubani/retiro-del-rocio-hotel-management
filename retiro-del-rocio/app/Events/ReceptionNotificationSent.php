<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new notification landed for the front desk.
 *
 * Deliberately a *signal*, not a payload — mirrors {@see GuestNotificationSent}:
 * the reception tablet is told a notification arrived and re-fetches
 * `GET /reception/notifications` with its own staff token, so nothing about the
 * notification's content ever travels over the public channel. Broadcasts on
 * the same hotel-wide `reception` channel {@see BookingChanged} already uses,
 * as its own event name, so no extra socket subscription is needed.
 */
class ReceptionNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('reception')];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
