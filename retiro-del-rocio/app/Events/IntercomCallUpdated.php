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
        public ?int $fromUserId,
        public ?int $toRoomUnitId,
        public ?string $toRole,
        public ?int $toUserId,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            $this->channelFor($this->fromRoomUnitId, $this->fromUserId, $this->fromRole),
            $this->channelFor($this->toRoomUnitId, $this->toUserId, $this->toRole),
        ];
    }

    private function channelFor(?int $roomUnitId, ?int $userId, ?string $role): Channel
    {
        return match (true) {
            $roomUnitId !== null => new Channel('rooms.'.$roomUnitId),
            $userId !== null => new Channel('staff-intercom.user.'.$userId),
            default => new Channel('staff-intercom.'.$role),
        };
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
