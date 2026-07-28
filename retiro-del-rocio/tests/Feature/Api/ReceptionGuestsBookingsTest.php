<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's Guests and Bookings modules: the guest list aggregated
 * from bookings, a single guest's profile with stay history and derived
 * preferences, and the read-only view of every room booking.
 */
class ReceptionGuestsBookingsTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence',
            'type' => 'suite',
            'price' => 150000,
        ]);
    }

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Front Desk']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@mail.com',
            'customer_phone' => '+44 7700 900111',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'paid',
        ], $overrides));
    }

    public function test_guests_are_aggregated_from_bookings_by_identity(): void
    {
        // Ada has two stays under the same email — one grouped guest.
        $this->booking(['check_in' => today()->subDays(30)->toDateString(), 'status' => 'checked_out']);
        $active = $this->booking(['status' => 'checked_in']);
        // A different guest.
        $this->booking(['customer_name' => 'Grace Hopper', 'customer_email' => 'grace@mail.com']);

        $data = $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $ada = collect($data)->firstWhere('name', 'Ada Lovelace');
        $this->assertSame(2, $ada['stays']);
        $this->assertTrue($ada['in_house']); // one booking is checked_in
        // The list can check her out directly, without opening her profile.
        $this->assertSame($active->id, $ada['active_booking_id']);

        $grace = collect($data)->firstWhere('name', 'Grace Hopper');
        $this->assertFalse($grace['in_house']);
        $this->assertNull($grace['active_booking_id']);
    }

    public function test_distinct_guests_sharing_one_contact_are_listed_separately(): void
    {
        // The real-world case: several different guests booked under one front-desk
        // email. They must each appear, not collapse into a single guest.
        $shared = 'frontdesk@hotel.com';
        $this->booking(['customer_name' => 'Ada Lovelace', 'customer_email' => $shared]);
        $this->booking(['customer_name' => 'Grace Hopper', 'customer_email' => $shared]);
        $this->booking(['customer_name' => 'Alan Turing', 'customer_email' => $shared]);
        // A genuine repeat by the same person still groups into one row.
        $this->booking(['customer_name' => 'Ada Lovelace', 'customer_email' => $shared, 'status' => 'checked_out']);

        $data = $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests')
            ->assertOk()
            ->assertJsonCount(3, 'data') // Ada, Grace, Alan — not one
            ->json('data');

        $ada = collect($data)->firstWhere('name', 'Ada Lovelace');
        $this->assertSame(2, $ada['stays']); // her two stays grouped
    }

    public function test_guest_search_narrows_the_list(): void
    {
        $this->booking(); // Ada
        $this->booking(['customer_name' => 'Grace Hopper', 'customer_email' => 'grace@mail.com']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests?search=grace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grace Hopper');
    }

    public function test_a_guest_profile_returns_history_stats_and_preferences(): void
    {
        $this->booking(['check_in' => today()->subDays(40)->toDateString(), 'status' => 'checked_out', 'nights' => 3, 'amount' => 450000]);
        $ada = $this->booking(['status' => 'checked_in', 'nights' => 2, 'amount' => 300000, 'pickup_vehicle' => 'Sedan']);

        // The guest key comes from the model, so the test doesn't hard-code its
        // format — both stays share Ada's name + email, so they group as one.
        $key = $ada->guestKey();

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests/profile?key='.urlencode($key))
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.in_house', true)
            // The desk can check her out from her profile, whatever her checkout
            // date — the same booking that made her in_house true.
            ->assertJsonPath('data.active_booking_id', $ada->id)
            ->assertJsonPath('data.stats.total_stays', 2)
            ->assertJsonPath('data.stats.total_nights', 5)
            ->assertJsonPath('data.preferences.favourite_room', 'Brisa Residence')
            ->assertJsonPath('data.preferences.usual_party_size', 2)
            ->assertJsonPath('data.preferences.uses_airport_pickup', true)
            ->assertJsonCount(2, 'data.history');
    }

    public function test_a_guest_not_in_house_has_no_active_booking(): void
    {
        $ada = $this->booking(['status' => 'checked_out']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests/profile?key='.urlencode($ada->guestKey()))
            ->assertOk()
            ->assertJsonPath('data.in_house', false)
            ->assertJsonPath('data.active_booking_id', null);
    }

    public function test_an_unknown_guest_key_is_not_found(): void
    {
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/guests/profile?key='.urlencode('email:nobody@mail.com'))
            ->assertNotFound();
    }

    public function test_bookings_lists_every_reservation_and_filters_by_status(): void
    {
        $this->booking(['status' => 'paid']);
        $this->booking(['customer_name' => 'Grace Hopper', 'status' => 'checked_in']);
        $this->booking(['customer_name' => 'Alan Turing', 'status' => 'cancelled']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bookings')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status_label', 'Cancelled'); // newest id first

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bookings?status=checked_in')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guest_name', 'Grace Hopper')
            ->assertJsonPath('data.0.status_label', 'Checked In');
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)->getJson('/api/v1/reception/guests')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/reception/bookings')->assertForbidden();
    }
}
