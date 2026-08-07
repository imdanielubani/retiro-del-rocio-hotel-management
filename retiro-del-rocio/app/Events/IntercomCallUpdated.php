<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An intercom call's status changed — answered, declined, cancelled or
 * ended. Broadcast to *both* parties' channels so whichever tablet isn't the
 * one that acted (the caller when the callee answers/declines, either side
 * when the other hangs up) updates the moment it happens rather than on its
 * next poll. A pure signal, same reasoning as {@see IntercomCallRinging}.
 */
class IntercomCallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?int $fromRoomUnitId,
        public ?string $fromRole,
        public ?int $toRoomUnitId,
        public ?string $toRole,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            $this->fromRoomUnitId !== null
                ? new Channel('rooms.'.$this->fromRoomUnitId)
                : new Channel('staff-intercom.'.$this->fromRole),
            $this->toRoomUnitId !== null
                ? new Channel('rooms.'.$this->toRoomUnitId)
                : new Channel('staff-intercom.'.$this->toRole),
        ];
    }

    public function broadcastAs(): string
    {
        return 'intercom.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
