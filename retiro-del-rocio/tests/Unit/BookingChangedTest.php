<?php

namespace Tests\Unit;

use App\Events\BookingChanged;
use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;

/**
 * The wire contract the reception tablet subscribes to. If the channel or event
 * name drifts, the tablet stops receiving live booking updates — so pin them.
 */
class BookingChangedTest extends TestCase
{
    public function test_it_broadcasts_a_signal_on_the_public_reception_channel(): void
    {
        $event = new BookingChanged(137, 'paid');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('reception', $channels[0]->name);

        // The Flutter SosChannel listens for exactly this event name.
        $this->assertSame('booking.changed', $event->broadcastAs());

        // Signal only — id + status, never guest details on the public channel.
        $this->assertSame(['id' => 137, 'status' => 'paid'], $event->broadcastWith());
    }
}
