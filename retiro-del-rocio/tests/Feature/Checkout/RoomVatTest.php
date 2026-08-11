<?php

namespace Tests\Feature\Checkout;

use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Website room checkout: VAT (7.5%) is added on top of the room subtotal at
 * Paystack payment time, then stored on the booking net of VAT with the VAT
 * kept on its own column — so room revenue never double-counts the tax.
 */
class RoomVatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret_key', 'sk_test_vat');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    }

    private function room(): Room
    {
        return Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-vat',
            'type' => 'suite',
            'price' => 100000,
        ]);
    }

    public function test_checkout_prices_the_room_with_vat_on_top(): void
    {
        $room = $this->room();

        $this->post('/checkout', [
            'room' => $room->name,
            'room_slug' => $room->slug,
            'price' => '₦100,000',
            'guests' => 2,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(), // 2 nights => 200,000 subtotal
        ])->assertRedirect(route('checkout'));

        $booking = session('booking');

        $this->assertSame(200000, $booking['subtotal']);
        $this->assertSame(15000, $booking['vat']); // 7.5% of 200,000
        $this->assertSame(215000, $booking['total']);
        $this->assertSame(21500000, $booking['total_kobo']); // charged to Paystack, incl VAT
    }

    public function test_the_callback_stores_the_booking_net_of_vat_with_vat_on_its_own_column(): void
    {
        // A confirmed booking triggers real TTLock provisioning, out of scope here.
        Event::fake([BookingConfirmed::class]);
        $room = $this->room();

        $this->post('/checkout', [
            'room' => $room->name,
            'room_slug' => $room->slug,
            'price' => '₦100,000',
            'guests' => 2,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('checkout'));

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 21500000, // matches the priced total_kobo (incl VAT)
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);

        $this->get('/checkout/callback?reference=vat-test-ref-1')->assertRedirect();

        $booking = Booking::where('reference', 'vat-test-ref-1')->first();
        $this->assertNotNull($booking);
        $this->assertSame(200000, (int) $booking->amount); // net of VAT — real room revenue
        $this->assertSame(15000, (int) $booking->vat);
    }

    public function test_the_checkout_and_success_pages_show_the_vat_line_to_the_guest(): void
    {
        Event::fake([BookingConfirmed::class]);
        $room = $this->room();

        $this->post('/checkout', [
            'room' => $room->name,
            'room_slug' => $room->slug,
            'price' => '₦100,000',
            'guests' => 2,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('checkout'));

        // The pre-payment checkout page shows VAT before the guest pays.
        $this->get('/checkout')
            ->assertOk()
            ->assertSee('VAT (7.5%)')
            ->assertSee('₦15,000') // the VAT amount
            ->assertSee('₦215,000'); // the total incl VAT

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 21500000,
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);
        $this->get('/checkout/callback?reference=vat-display-ref-1')->assertRedirect();

        // The post-payment success page also breaks down VAT for the guest's record.
        $this->get('/reservation-successful')
            ->assertOk()
            ->assertSee('VAT (7.5%)')
            ->assertSee('₦15,000')
            ->assertSee('₦215,000');
    }

    public function test_the_callback_rejects_a_charge_for_the_wrong_amount(): void
    {
        Event::fake([BookingConfirmed::class]);
        $room = $this->room();

        $this->post('/checkout', [
            'room' => $room->name,
            'room_slug' => $room->slug,
            'price' => '₦100,000',
            'guests' => 2,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('checkout'));

        // Paystack confirms "success" but the amount actually charged is far
        // below what the booking costs — a tampered client-side amount.
        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 10000, // ₦100, not the ₦215,000 expected
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);

        $this->get('/checkout/callback?reference=vat-mismatch-ref-1')->assertRedirect(route('checkout'));

        $this->assertNull(Booking::where('reference', 'vat-mismatch-ref-1')->first());
    }
}
