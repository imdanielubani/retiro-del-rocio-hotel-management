<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new notification landed for this room's guest.
 *
 * Deliberately a *signal*, not a payload — mirrors {@see RoomStatusChanged}:
 * the tablet is told its room has a new notification and re-fetches
 * `GET /v1/tablets/notifications` with its own device token, so nothing about
 * the notification's content ever travels over the public channel. Broadcasts
 * on the same `rooms.{roomUnitId}` channel the tablet is already subscribed
 * to for room-status updates, so no extra socket subscription is needed.
 */
class GuestNotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $roomUnitId) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('rooms.'.$this->roomUnitId)];
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
