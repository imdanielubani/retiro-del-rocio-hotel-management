<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new intercom call is ringing for a callee — a guest's room, a staff
 * role (a guest calling the front desk, which any reception account may
 * answer), or one specific staff member. Deliberately a *signal*, not a
 * payload (mirrors {@see GuestNotificationSent}): the callee's tablet is
 * told a call is ringing and fetches the call's details with its own
 * token, so nothing about who's calling ever travels over the public
 * channel.
 */
class IntercomCallRinging implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ?int $toRoomUnitId, public ?string $toRole, public ?int $toUserId = null) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            match (true) {
                $this->toRoomUnitId !== null => new Channel('rooms.'.$this->toRoomUnitId),
                $this->toUserId !== null => new Channel('staff-intercom.user.'.$this->toUserId),
                default => new Channel('staff-intercom.'.$this->toRole),
            },
        ];
    }

    public function broadcastAs(): string
    {
        return 'intercom.ringing';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
