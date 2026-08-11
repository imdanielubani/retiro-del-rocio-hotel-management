<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Bookings\Index;
use App\Models\Booking;
use App\Models\StayExtensionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Bookings: an "Extended" indicator marks reservations the guest has
 * extended (a paid stay extension exists).
 */
class BookingExtensionIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    private function booking(string $name): Booking
    {
        return Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => $name,
            'room_name' => 'Alba Suite',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 37500,
            'status' => 'checked_in',
        ]);
    }

    private function extend(Booking $booking, int $nights, string $status): void
    {
        StayExtensionPayment::create([
            'booking_id' => $booking->id,
            'reference' => 'EXT-'.$booking->id.'-'.Str::upper(Str::random(6)),
            'nights' => $nights,
            'new_check_out' => now()->addDays(4 + $nights)->toDateString(),
            'amount' => $nights * 7500,
            'vat' => 0,
            'status' => $status,
            'paid_at' => $status === StayExtensionPayment::SUCCESS ? now() : null,
        ]);
    }

    public function test_the_bookings_list_flags_an_extended_stay(): void
    {
        $extended = $this->booking('Extended Guest');
        $this->extend($extended, 2, StayExtensionPayment::SUCCESS);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Extended');
    }

    public function test_a_plain_booking_has_no_indicator(): void
    {
        $this->booking('Plain Guest');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertOk()
            ->assertDontSee('Extended');
    }

    public function test_a_pending_extension_does_not_flag_the_booking(): void
    {
        // The guest opened Paystack but never completed the charge.
        $booking = $this->booking('Abandoned Guest');
        $this->extend($booking, 3, StayExtensionPayment::PENDING);

        $this->assertFalse($booking->fresh()->wasExtended());
        $this->assertSame(0, $booking->fresh()->extensionNights());
    }

    public function test_the_model_reports_the_extension_nights(): void
    {
        $booking = $this->booking('Counted Guest');
        $this->extend($booking, 2, StayExtensionPayment::SUCCESS);
        $this->extend($booking, 1, StayExtensionPayment::SUCCESS);

        $this->assertTrue($booking->fresh()->wasExtended());
        $this->assertSame(3, $booking->fresh()->extensionNights());
        $this->assertSame('Extended by 3 nights', $booking->fresh()->extensionSummary());
    }
}
