<?php

namespace Tests\Feature\Checkout;

use App\Models\CinemaBooking;
use App\Models\GymMembership;
use App\Models\GymPlan;
use App\Models\Movie;
use App\Models\RestaurantReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Website gym, restaurant and cinema checkouts: VAT (7.5%) is charged on top of
 * the service price at Paystack payment time. These flows price server-side, so
 * VAT is verified on the persisted record — net amount plus its own VAT column.
 */
class ServiceVatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret_key', 'sk_test_vat');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    }

    private function fakeVerify(int $amountKobo): void
    {
        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'channel' => 'card',
                    'amount' => $amountKobo,
                    'paid_at' => now()->toIso8601String(),
                ],
            ], 200),
        ]);
    }

    public function test_a_gym_membership_is_priced_with_vat(): void
    {
        $plan = GymPlan::create([
            'name' => 'Premium', 'slug' => 'premium-vat', 'price' => 20000, 'period' => 'monthly',
        ]);
        $this->fakeVerify(2150000); // ₦21,500 (20,000 + 7.5% VAT), in kobo

        $this->post('/gym/subscribe', [
            'reference' => 'gym-vat-ref-1',
            'plan' => $plan->slug,
            'type' => 'subscribe',
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('gym'));

        $membership = GymMembership::where('reference', 'gym-vat-ref-1')->first();
        $this->assertNotNull($membership);
        $this->assertSame(20000, (int) $membership->price);
        $this->assertSame(1500, (int) $membership->vat); // 7.5% of 20,000
    }

    public function test_a_restaurant_reservation_is_priced_with_vat(): void
    {
        $this->fakeVerify(1075000); // ₦10,750 (10,000 + 7.5% VAT), in kobo

        $this->post('/restaurant-bar/reserve', [
            'reference' => 'rest-vat-ref-1',
            'area' => 'dining',
            'guests' => 2,
            'date' => now()->addDay()->toDateString(),
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('restaurant'));

        $reservation = RestaurantReservation::where('reference', 'rest-vat-ref-1')->first();
        $this->assertNotNull($reservation);
        // Default reservation fee is 10,000 when no CMS override is seeded.
        $this->assertSame(10000, (int) $reservation->fee);
        $this->assertSame(750, (int) $reservation->vat); // 7.5% of 10,000
    }

    public function test_a_cinema_booking_is_priced_with_vat(): void
    {
        $movie = Movie::create([
            'title' => 'Test Feature', 'slug' => 'test-feature-vat',
            'genre' => 'Drama', 'duration' => 120, 'rating' => 'PG',
            'adult_price' => 5000, 'child_price' => 3000, 'room_price' => 50000,
            'is_active' => true,
        ]);
        $this->fakeVerify(5375000); // ₦53,750 (50,000 + 7.5% VAT), in kobo

        $this->post('/cinema/book', [
            'reference' => 'cinema-vat-ref-1',
            'movie' => $movie->slug,
            'date' => now()->addDay()->toDateString(),
            'time' => '18:00',
            'room' => 'Room 1',
            'guests' => 2,
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect();

        $booking = CinemaBooking::where('reference', 'cinema-vat-ref-1')->first();
        $this->assertNotNull($booking);
        $this->assertSame(50000, (int) $booking->amount); // net of VAT
        $this->assertSame(3750, (int) $booking->vat); // 7.5% of 50,000
    }

    public function test_the_gym_page_shows_the_vat_line_to_the_guest(): void
    {
        GymPlan::create(['name' => 'Premium', 'slug' => 'premium-display', 'price' => 20000, 'period' => 'monthly']);

        // Amounts are computed client-side (Alpine x-text), so the server HTML
        // only carries the static label — this confirms the markup now exists.
        $this->get('/gym')->assertOk()->assertSee('VAT (7.5%)');
    }

    public function test_the_restaurant_page_shows_the_vat_line_to_the_guest(): void
    {
        $this->get('/restaurant-bar')->assertOk()->assertSee('VAT (7.5%)');
    }

    public function test_the_cinema_movie_page_shows_the_vat_line_to_the_guest(): void
    {
        $movie = Movie::create([
            'title' => 'Test Feature Display', 'slug' => 'test-feature-vat-display',
            'genre' => 'Drama', 'duration' => 120, 'rating' => 'PG',
            'adult_price' => 5000, 'child_price' => 3000, 'room_price' => 50000,
            'is_active' => true,
        ]);

        $this->get('/cinema/'.$movie->slug)->assertOk()->assertSee('VAT (7.5%)');
    }

    public function test_a_charge_for_the_wrong_amount_is_rejected(): void
    {
        $plan = GymPlan::create([
            'name' => 'Premium', 'slug' => 'premium-mismatch', 'price' => 20000, 'period' => 'monthly',
        ]);
        // Paystack confirms "success", but the amount actually charged is far
        // below the ₦21,500 (price + VAT) this plan costs — a tampered charge.
        $this->fakeVerify(100);

        $this->post('/gym/subscribe', [
            'reference' => 'gym-vat-mismatch-1',
            'plan' => $plan->slug,
            'type' => 'subscribe',
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('gym'));

        $this->assertNull(GymMembership::where('reference', 'gym-vat-mismatch-1')->first());
    }
}
