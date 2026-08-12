<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new Staff Chat message landed for [toUserId] — signal-only, mirrors
 * {@see ReceptionNotificationSent}: the recipient's tablet re-fetches
 * `GET /staff/chat/channels` (and its open thread, if any) with its own
 * token, so no message content ever travels over the public channel.
 *
 * Broadcasts on a channel scoped to the *recipient alone*, unlike
 * {@see StaffChatTypingSent}'s per-pair channel — a new message has to reach
 * that person wherever they are in the app, not just while that one thread
 * happens to be open.
 */
class StaffChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $toUserId, public int $fromUserId) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('staff-chat-inbox.user.'.$this->toUserId)];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['from_user_id' => $this->fromUserId];
    }
}
