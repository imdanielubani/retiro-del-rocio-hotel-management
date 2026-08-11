<?php

namespace Tests\Feature\Checkout;

use App\Models\SpaBooking;
use App\Models\SpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Website spa checkout: VAT (7.5%) is added on top of the services subtotal at
 * Paystack payment time, then stored net of VAT with the VAT on its own column.
 */
class SpaVatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret_key', 'sk_test_vat');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    }

    private function service(): SpaService
    {
        return SpaService::create([
            'name' => 'Deep Tissue Massage',
            'slug' => 'deep-tissue-massage',
            'price' => 40000,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_the_reservation_summary_prices_with_vat_on_top(): void
    {
        $service = $this->service();

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        $booking = session('spa_booking');

        $this->assertSame(40000, $booking['subtotal']);
        $this->assertSame(3000, $booking['vat']); // 7.5% of 40,000
        $this->assertSame(43000, $booking['total']);
        $this->assertSame(4300000, $booking['total_kobo']);
    }

    public function test_the_callback_stores_the_spa_booking_net_of_vat(): void
    {
        $service = $this->service();

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 4300000, // matches the priced total_kobo (incl VAT)
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);

        $this->get('/spa-wellness/callback?reference=spa-vat-ref-1')->assertRedirect();

        $spaBooking = SpaBooking::where('reference', 'spa-vat-ref-1')->first();
        $this->assertNotNull($spaBooking);
        $this->assertSame(40000, (int) $spaBooking->total); // net of VAT
        $this->assertSame(3000, (int) $spaBooking->vat);
    }

    public function test_the_checkout_and_success_pages_show_the_vat_line_to_the_guest(): void
    {
        $service = $this->service();

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        // The pre-payment checkout step (rendered on the spa page) shows VAT.
        $this->get('/spa-wellness')
            ->assertOk()
            ->assertSee('VAT (7.5%)')
            ->assertSee('₦3,000') // the VAT amount
            ->assertSee('₦43,000'); // the total incl VAT

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 4300000,
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);
        $this->get('/spa-wellness/callback?reference=spa-vat-display-1')->assertRedirect();

        // The success step also shows VAT for the guest's record.
        $this->get('/spa-wellness')
            ->assertOk()
            ->assertSee('VAT (7.5%)')
            ->assertSee('₦3,000')
            ->assertSee('₦43,000');
    }

    public function test_an_unfinished_checkout_reopens_on_refresh_until_dismissed(): void
    {
        $service = $this->service();

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        // Right after submitting, the guest is actively mid-checkout (e.g. still
        // filling in their name/email) — a refresh must not lose that, so the
        // popup correctly reopens on the checkout step.
        $this->assertStringContainsString("step: 'checkout'", $this->get('/spa-wellness')->getContent());

        // The guest dismisses the popup without paying. spaReservation.close()
        // fires this reset in the background — it must clear the pending
        // booking so it stops reopening checkout on every later visit/refresh.
        $this->get('/spa-wellness/reset', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull(session('spa_booking'));

        // A later visit now shows the normal browsing page, not checkout.
        $this->assertStringContainsString("step: 'select'", $this->get('/spa-wellness')->getContent());
    }

    public function test_the_reset_endpoint_redirects_for_a_direct_browser_visit(): void
    {
        // A plain navigation (no Accept: application/json) still works as a
        // normal link/redirect, for any direct (non-fetch) use of the route.
        $this->get('/spa-wellness/reset')->assertRedirect(route('spa'));
    }

    public function test_the_callback_rejects_a_charge_for_the_wrong_amount(): void
    {
        $service = $this->service();

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => 1000, // far below the ₦43,000 expected
                    'paid_at' => now()->toIso8601String(),
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);

        $this->get('/spa-wellness/callback?reference=spa-vat-mismatch-1')->assertRedirect(route('spa'));

        $this->assertNull(SpaBooking::where('reference', 'spa-vat-mismatch-1')->first());
    }
}
