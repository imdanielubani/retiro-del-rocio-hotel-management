<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Someone is typing in a staff-to-staff chat channel — a pure, ephemeral
 * signal, never persisted anywhere (mirrors {@see ChatTypingSent}, the
 * guest/reception equivalent). Broadcasts on a channel keyed by the two
 * roles involved, so only the two stations in that conversation ever
 * subscribe to it.
 */
class StaffChatTypingSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  string  $from  The sender's own role, e.g. 'maintenance' */
    public function __construct(public string $channelKey, public string $from) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('staff-chat.'.$this->channelKey)];
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
