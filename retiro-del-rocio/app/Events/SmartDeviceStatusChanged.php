<?php

namespace App\Events;

use App\Models\SmartDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A smart device's state changed — a guest sent a command, or a scheduled
 * status sync picked up a change made outside our system.
 *
 * Deliberately a *signal*, not a payload: the tablet is told a device in its
 * room changed and re-fetches the device list with its own device token.
 * Reuses the room's existing `rooms.{id}` channel (same one `RoomStatusChanged`
 * broadcasts on) rather than a new one, so no new channel-auth wiring is
 * needed.
 *
 * Broadcast now rather than queued: a guest is standing in the room waiting
 * to see the light come on.
 */
class SmartDeviceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $roomUnitId,
        public int $smartDeviceId,
    ) {}

    public static function forDevice(SmartDevice $device): self
    {
        return new self((int) $device->room_unit_id, $device->id);
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('rooms.'.$this->roomUnitId)];
    }

    public function broadcastAs(): string
    {
        return 'smart_device.status_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['smart_device_id' => $this->smartDeviceId];
    }
}
