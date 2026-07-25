<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * A booking was created or changed status — a new reservation, a confirmation, a
 * check-in, a check-out or a cancellation.
 *
 * A *signal*, not a payload (id + status only), broadcast on the hotel-wide
 * public `reception` channel. The reception tablet is told a booking changed and
 * re-fetches its lists with its own token, so no guest name, email or phone ever
 * rides the public channel. Broadcast now rather than queued: the front desk is
 * watching the screen.
 */
class BookingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $bookingId,
        public string $status,
    ) {}

    /** Fire-and-forget; a broadcaster being down must never break a booking write. */
    public static function announce(Booking $booking): void
    {
        try {
            broadcast(new self($booking->id, (string) $booking->status));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('reception')];
    }

    public function broadcastAs(): string
    {
        return 'booking.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['id' => $this->bookingId, 'status' => $this->status];
    }
}
