<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new notification landed for the bar (e.g. a new drink order).
 *
 * Deliberately a *signal*, not a payload — mirrors {@see MaintenanceNotificationSent}:
 * the Bar Tablet is told a notification arrived and re-fetches
 * `GET /bar/notifications` with its own staff token.
 */
class BarNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('bar')];
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
