<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * A booking's gate-pass (TTLock) status changed — being generated, active,
 * partial, failed or revoked.
 *
 * A *signal*, not a payload: it carries only the booking id and the new status,
 * never the passcode or any guest detail. The admin dashboard re-fetches the
 * register itself when it hears this, so the Access Gate Passes list refreshes
 * on its own — no manual page reload — while nothing sensitive travels over the
 * socket.
 *
 * Broadcast now (not queued): it fires from inside the provisioning jobs and the
 * front desk should see the result the moment it lands. Use announce() to send
 * it — broadcasting must never break provisioning if Reverb is unreachable.
 */
class TtlockAccessChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $bookingId,
        public string $status,
    ) {}

    /**
     * Broadcast the change, swallowing any transport error so a down Reverb can
     * never fail (or falsely fail) the provisioning that triggered it.
     */
    public static function announce(int $bookingId, ?string $status): void
    {
        if (! $status) {
            return;
        }

        try {
            broadcast(new self($bookingId, $status));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('admin')];
    }

    public function broadcastAs(): string
    {
        return 'ttlock.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->bookingId, 'status' => $this->status];
    }
}
