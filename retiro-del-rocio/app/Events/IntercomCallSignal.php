<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Relays a WebRTC signaling message — an SDP offer/answer, or an ICE
 * candidate — between the two tablets on an Intercom call.
 *
 * Unlike every other broadcast event in this app, this one carries a real
 * payload rather than being a pure "go re-fetch" signal: the two devices
 * have no other channel through which to exchange the session description
 * / candidate data needed to open their direct peer-to-peer audio
 * connection, and re-fetching from the database isn't an option — this data
 * is never persisted, it only ever exists in flight.
 *
 * Scoped to the call's own id rather than either party's identity, so only
 * the two devices that already know this specific call exists (both of
 * which received the id from `toCallArray()`) have any reason to subscribe.
 */
class IntercomCallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $from  'caller' or 'callee' — which side of the call sent
     *                        this, so the recipient can ignore its own echo
     *                        without needing to know the other side's identity.
     * @param  string  $type  'offer', 'answer', or 'ice-candidate'.
     * @param  array<string, mixed>  $data  The SDP or ICE candidate payload, opaque to the server.
     */
    public function __construct(
        public int $callId,
        public string $from,
        public string $type,
        public array $data,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('intercom-signal.'.$this->callId)];
    }

    public function broadcastAs(): string
    {
        return 'signal';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['from' => $this->from, 'type' => $this->type, 'data' => $this->data];
    }
}
