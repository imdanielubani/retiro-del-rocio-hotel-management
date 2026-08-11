<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone is typing in a guest's Concierge Chat thread — a pure, ephemeral
 * signal, never persisted anywhere. Broadcasts on the same `rooms.{id}`
 * channel the guest tablet is already subscribed to for room-status and
 * notification updates, so no extra socket subscription is needed on the
 * guest side; reception opens a transient subscription to this same channel
 * only while that guest's thread is on screen.
 *
 * Unlike {@see GuestNotificationSent} this carries a payload — [from] — since
 * there's nothing to re-fetch: the signal *is* the whole message, and it's
 * already public information (it says nothing beyond "someone is typing").
 */
class ChatTypingSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  'guest'|'staff'  $from */
    public function __construct(public int $roomUnitId, public string $from) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('rooms.'.$this->roomUnitId)];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['from' => $this->from];
    }
}
