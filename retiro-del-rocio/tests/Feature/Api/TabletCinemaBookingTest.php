<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\CinemaBooking;
use App\Models\CinemaSeatHold;
use App\Models\CinemaSnack;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Movie;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guest tablet's Cinema screen: browsing movies/snacks, charging a
 * private room straight to the room, and paying directly via Paystack.
 */
class TabletCinemaBookingTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    private Booking $booking;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Alba Suite',
            'slug' => 'alba-suite-cinema',
            'type' => 'suite',
            'price' => 7500,
            'guests' => 4,
            'amenities' => ['High-Speed WiFi'],
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '101',
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 3,
            'amount' => 37500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-cinema']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-CIN-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $device->createToken('tablet')->plainTextToken;
    }

    private function movie(array $overrides = []): Movie
    {
        return Movie::create(array_merge([
            'title' => 'The Great Escape',
            'slug' => 'the-great-escape-'.Str::random(6),
            'genre' => 'Action',
            'duration' => '2h 10m',
            'room_price' => 40000,
            'classification' => 'now_showing',
            'showtimes' => ['2:00 PM', '6:00 PM'],
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    private function snack(array $overrides = []): CinemaSnack
    {
        return CinemaSnack::create(array_merge([
            'name' => 'Large Popcorn',
            'price' => 3500,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_the_catalog_lists_active_movies_and_snacks(): void
    {
        $this->movie(['title' => 'Active Movie']);
        $this->movie(['title' => 'Retired Movie', 'is_active' => false]);
        $this->snack(['name' => 'Active Snack']);
        $this->snack(['name' => 'Retired Snack', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/cinema/movies')
            ->assertOk()
            ->assertJsonCount(1, 'data.movies')
            ->assertJsonPath('data.movies.0.title', 'Active Movie')
            ->assertJsonPath('data.movies.0.room_price_label', 'NGN 40,000')
            ->assertJsonCount(1, 'data.snacks')
            ->assertJsonPath('data.snacks.0.name', 'Active Snack')
            ->assertJsonPath('data.rooms', Movie::ROOMS)
            ->assertJsonPath('data.seats_per_room', Movie::SEATS_PER_ROOM);
    }

    public function test_charging_a_room_to_the_room_confirms_it_immediately(): void
    {
        $movie = $this->movie();
        $snack = $this->snack();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/book', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 2,
                'snacks' => [['id' => $snack->id, 'qty' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.movie_title', $movie->title)
            ->assertJsonPath('data.room', 'Room 1')
            // 40,000 room + (3,500 × 2 snacks) = 47,000 + 7.5% VAT = 50,525
            ->assertJsonPath('data.total_label', 'NGN 50,525')
            ->assertJsonPath('data.payment_method', 'room_charge');

        $booking = CinemaBooking::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame('confirmed', $booking->status);
        // Charged to the room folio, not actually paid yet — it must not
        // register as revenue in the admin Payments module until settled.
        $this->assertSame('pending', $booking->payment_status);
        $this->assertNull($booking->paid_at);
        $this->assertSame(47000, (int) $booking->subtotal);
        $this->assertSame(3525, (int) $booking->vat);
        $this->assertSame([['name' => 'Large Popcorn', 'qty' => 2, 'price' => 3500]], $booking->snacks);

        // The room is now locked for that showing.
        $this->assertContains('Room 1', CinemaSeatHold::takenSeats($movie->id, now()->addDay()->toDateString(), '2:00 PM'));

        // Both the guest's own feed and the front desk hear about it.
        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'cinema')
            ->assertJsonPath('data.0.title', 'Cinema Booking Confirmed');

        $this->assertSame(
            1,
            ReceptionNotification::where('category', 'booking')->where('title', 'New Cinema Booking')->count()
        );
    }

    public function test_a_room_already_taken_cannot_be_charged_to_room(): void
    {
        $movie = $this->movie();
        $date = now()->addDay()->toDateString();

        CinemaSeatHold::create([
            'movie_id' => $movie->id,
            'show_date' => $date,
            'show_time' => '2:00 PM',
            'seat' => 'Room 1',
            'token' => 'other-guest',
            'cinema_booking_id' => null,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/book', [
                'movie_slug' => $movie->slug,
                'date' => $date,
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 1,
            ])
            ->assertStatus(422);

        $this->assertSame(0, CinemaBooking::where('booking_id', $this->booking->id)->count());
    }

    public function test_room_availability_lists_taken_rooms(): void
    {
        $movie = $this->movie();
        $date = now()->addDay()->toDateString();

        CinemaSeatHold::create([
            'movie_id' => $movie->id,
            'show_date' => $date,
            'show_time' => '2:00 PM',
            'seat' => 'Room 2',
            'token' => 'someone',
            'cinema_booking_id' => null,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/tablets/cinema/{$movie->slug}/availability?date={$date}&time=".urlencode('2:00 PM'))
            ->assertOk()
            ->assertJsonPath('data.taken_rooms', ['Room 2']);
    }

    public function test_bookings_lists_confirmed_bookings_newest_first(): void
    {
        $movieA = $this->movie(['title' => 'Movie A']);
        $movieB = $this->movie(['title' => 'Movie B']);

        CinemaBooking::create([
            'booking_id' => $this->booking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-APPT-1',
            'movie_id' => $movieA->id,
            'movie_title' => $movieA->title,
            'show_date' => now()->subDays(2)->toDateString(),
            'show_time' => '2:00 PM',
            'room' => 'Room 1',
            'guests' => 2,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000,
            'amount' => 40000,
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now()->subDays(2),
        ]);
        CinemaBooking::create([
            'booking_id' => $this->booking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-APPT-2',
            'movie_id' => $movieB->id,
            'movie_title' => $movieB->title,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '6:00 PM',
            'room' => 'Room 2',
            'guests' => 1,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000,
            'amount' => 40000,
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/cinema/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', 'CIN-APPT-2')
            ->assertJsonPath('data.0.movie_title', 'Movie B')
            ->assertJsonPath('data.0.payment_method_label', 'Paid Online')
            ->assertJsonPath('data.0.charged_to_room', false)
            ->assertJsonPath('data.1.reference', 'CIN-APPT-1')
            ->assertJsonPath('data.1.payment_method_label', 'Charged to Room')
            ->assertJsonPath('data.1.charged_to_room', true);
    }

    public function test_bookings_excludes_an_abandoned_unpaid_paystack_checkout(): void
    {
        $movie = $this->movie();

        CinemaBooking::create([
            'booking_id' => $this->booking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-ABANDONED-1',
            'movie_id' => $movie->id,
            'movie_title' => $movie->title,
            'show_date' => now()->toDateString(),
            'show_time' => '2:00 PM',
            'room' => 'Room 1',
            'guests' => 1,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000,
            'amount' => 40000,
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/cinema/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_bookings_only_shows_the_active_guests_own_bookings(): void
    {
        $movie = $this->movie();
        $otherUnit = RoomUnit::create(['room_id' => $this->room->id, 'number' => '102', 'status' => 'occupied']);
        $otherBooking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Another Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $otherUnit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 1,
            'amount' => 22500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        CinemaBooking::create([
            'booking_id' => $otherBooking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-FOREIGN-1',
            'movie_id' => $movie->id,
            'movie_title' => $movie->title,
            'show_date' => now()->toDateString(),
            'show_time' => '2:00 PM',
            'room' => 'Room 1',
            'guests' => 1,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000,
            'amount' => 40000,
            'customer_name' => 'Another Guest',
            'customer_email' => 'another@example.test',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/cinema/bookings')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_inactive_movie_cannot_be_booked(): void
    {
        $movie = $this->movie(['is_active' => false]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/book', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 1,
            ])
            ->assertNotFound();
    }

    public function test_an_invalid_room_is_rejected(): void
    {
        $movie = $this->movie();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/book', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 99',
                'guests' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_snacks_are_priced_from_the_live_catalogue_not_the_client(): void
    {
        $movie = $this->movie();
        $snack = $this->snack(['price' => 3500]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/book', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 1,
                // A client-supplied price of 1 must be ignored server-side.
                'snacks' => [['id' => $snack->id, 'qty' => 1, 'price' => 1]],
            ])
            ->assertOk();

        $booking = CinemaBooking::where('booking_id', $this->booking->id)->first();
        $this->assertSame([['name' => 'Large Popcorn', 'qty' => 1, 'price' => 3500]], $booking->snacks);
    }

    public function test_paying_via_paystack_confirms_the_booking_after_verification(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_cinema');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $movie = $this->movie();

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 4300000, 'channel' => 'card'],
            ], 200),
        ]);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/initialize', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.total_label', 'NGN 43,000') // 40,000 + 7.5% VAT
            ->json('data.reference');

        // Not yet confirmed — still pending until the charge is verified.
        $this->assertSame('pending', CinemaBooking::where('reference', $reference)->first()->payment_status);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.room', 'Room 1');

        $booking = CinemaBooking::where('reference', $reference)->first();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_a_paystack_charge_for_the_wrong_amount_is_rejected(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_cinema');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $movie = $this->movie();

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 100, 'channel' => 'card'],
            ], 200),
        ]);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/initialize', [
                'movie_slug' => $movie->slug,
                'date' => now()->addDay()->toDateString(),
                'time' => '2:00 PM',
                'room' => 'Room 1',
                'guests' => 1,
            ])
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/confirm', ['reference' => $reference])
            ->assertStatus(402);

        $this->assertSame('pending', CinemaBooking::where('reference', $reference)->first()->payment_status);
    }

    public function test_a_guest_cannot_confirm_another_bookings_reference(): void
    {
        $movie = $this->movie();
        $otherUnit = RoomUnit::create(['room_id' => $this->room->id, 'number' => '102', 'status' => 'occupied']);
        $otherBooking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Another Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $otherUnit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 1,
            'amount' => 22500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $foreign = CinemaBooking::create([
            'booking_id' => $otherBooking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-FOREIGN-2',
            'movie_id' => $movie->id,
            'movie_title' => $movie->title,
            'show_date' => now()->toDateString(),
            'show_time' => '2:00 PM',
            'room' => 'Room 1',
            'guests' => 1,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000,
            'amount' => 40000,
            'customer_name' => 'Another Guest',
            'customer_email' => 'another@example.test',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/cinema/confirm', ['reference' => $foreign->reference])
            ->assertNotFound();
    }
}
