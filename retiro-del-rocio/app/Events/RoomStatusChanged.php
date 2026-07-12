<?php

namespace App\Events;

use App\Models\RoomUnit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A room's occupancy changed — reception checked a guest in or out.
 *
 * Deliberately a *signal*, not a payload: the tablet is told which room changed
 * and re-fetches `GET /v1/tablets/room-status` with its own device token. That
 * keeps guest names and booking details off the socket entirely, so the channel
 * can stay public and no tablet can ever learn about a room it isn't bound to.
 *
 * Broadcast now rather than queued: a guest is standing in the room.
 */
class RoomStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $roomUnitId,
        public string $occupancy,
    ) {}

    public static function forUnit(RoomUnit $unit): self
    {
        return new self($unit->id, (string) $unit->status);
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('rooms.'.$this->roomUnitId)];
    }

    public function broadcastAs(): string
    {
        return 'room.status.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['occupancy' => $this->occupancy];
    }
}
