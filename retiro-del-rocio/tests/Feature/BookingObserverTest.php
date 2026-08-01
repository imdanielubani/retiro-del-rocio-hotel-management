<?php

namespace Tests\Feature;

use App\Events\BookingChanged;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BookingObserver pushes a live `booking.changed` signal to the reception
 * tablet's already-open dashboard. A stay extension changes `check_out`
 * without ever changing `status` (the guest stays checked_in throughout), so
 * that column has to be watched on its own — otherwise the front desk keeps
 * seeing a guest under their stale, pre-extension checkout date until
 * something else happens to trigger a refetch.
 */
class BookingObserverTest extends TestCase
{
    use RefreshDatabase;

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Grace Hopper',
            'room_name' => 'Alba Suite',
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->toDateString(),
            'nights' => 1,
            'guests' => 1,
            'amount' => 150000,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_extending_the_checkout_date_broadcasts_a_booking_changed_signal(): void
    {
        Event::fake([BookingChanged::class]);

        $booking = $this->booking();
        Event::assertDispatched(BookingChanged::class, 1); // the create() itself

        $booking->update(['check_out' => today()->addDay()->toDateString()]);

        Event::assertDispatched(BookingChanged::class, 2);
    }

    public function test_a_change_that_does_not_touch_status_or_check_out_does_not_broadcast(): void
    {
        $booking = $this->booking();

        Event::fake([BookingChanged::class]);

        $booking->update(['guests' => 2]);

        Event::assertNotDispatched(BookingChanged::class);
    }

    public function test_a_status_change_still_broadcasts(): void
    {
        $booking = $this->booking(['status' => 'paid', 'checked_in_at' => null]);

        Event::fake([BookingChanged::class]);

        $booking->update(['status' => 'checked_in', 'checked_in_at' => now()]);

        Event::assertDispatched(BookingChanged::class, 1);
    }
}
