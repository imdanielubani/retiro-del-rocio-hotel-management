<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An emergency alert was raised, acknowledged, resolved or cancelled.
 *
 * A *signal*, not a payload — deliberately carries no guest name or room. The
 * security tablet re-fetches the alert with its own device token, so no guest's
 * emergency details ever travel over a socket, and the room's own tablet cannot
 * learn about emergencies in other rooms.
 *
 * Broadcast now, not queued: someone is waiting for help.
 */
class SosAlertChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $alertId,
        public string $status,
        public ?int $roomUnitId = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('sos'),        // every security tablet
            new Channel('admin'),      // the dashboard
        ];

        // The room that raised it, so the guest's own tablet follows its state.
        if ($this->roomUnitId) {
            $channels[] = new Channel('rooms.'.$this->roomUnitId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'sos.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->alertId, 'status' => $this->status];
    }
}
