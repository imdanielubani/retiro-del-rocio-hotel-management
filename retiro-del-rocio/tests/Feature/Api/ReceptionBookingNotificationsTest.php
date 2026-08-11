<?php

namespace Tests\Feature\Api;

use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\GymPlan;
use App\Models\Movie;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The front desk must hear about every paying guest, whichever door they came
 * through: a service booked on the public website (room, spa, gym, restaurant,
 * cinema) or an action taken from a guest's own in-room tablet (a visitor pass
 * — stay extension is covered by ReceptionNotificationsTest).
 */
class ReceptionBookingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.paystack.secret_key', 'sk_test_reception');
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
                    'metadata' => ['name' => 'Daniel Ubani', 'phone' => '08000000000'],
                    'customer' => ['email' => 'daniel@example.test'],
                ],
            ], 200),
        ]);
    }

    public function test_a_paid_website_room_booking_notifies_the_front_desk(): void
    {
        Event::fake([BookingConfirmed::class]);
        $room = Room::create(['name' => 'Alba Suite', 'slug' => 'alba-suite-recep-notify', 'type' => 'suite', 'price' => 100000]);

        $this->post('/checkout', [
            'room' => $room->name,
            'room_slug' => $room->slug,
            'price' => '₦100,000',
            'guests' => 2,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ])->assertRedirect(route('checkout'));

        $this->fakeVerify(21500000);
        $this->get('/checkout/callback?reference=recep-room-ref-1')->assertRedirect();

        $this->assertSame(1, ReceptionNotification::where('category', 'booking')->where('title', 'New Room Booking')->count());
    }

    public function test_a_paid_website_spa_booking_notifies_the_front_desk(): void
    {
        $service = SpaService::create([
            'name' => 'Deep Tissue Massage', 'slug' => 'dtm-recep-notify',
            'price' => 40000, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->post('/spa-wellness/reserve', [
            'services' => [$service->slug],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
        ])->assertRedirect(route('spa'));

        $this->fakeVerify(4300000);
        $this->get('/spa-wellness/callback?reference=recep-spa-ref-1')->assertRedirect();

        $this->assertSame(1, ReceptionNotification::where('category', 'booking')->where('title', 'New Spa Booking')->count());
    }

    public function test_a_paid_website_gym_membership_notifies_the_front_desk(): void
    {
        $plan = GymPlan::create(['name' => 'Premium', 'slug' => 'premium-recep-notify', 'price' => 20000, 'period' => 'monthly']);
        $this->fakeVerify(2150000);

        $this->post('/gym/subscribe', [
            'reference' => 'recep-gym-ref-1',
            'plan' => $plan->slug,
            'type' => 'subscribe',
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('gym'));

        $this->assertSame(1, ReceptionNotification::where('category', 'booking')->where('title', 'New Gym Membership')->count());
    }

    public function test_a_paid_website_restaurant_reservation_notifies_the_front_desk(): void
    {
        $this->fakeVerify(1075000);

        $this->post('/restaurant-bar/reserve', [
            'reference' => 'recep-rest-ref-1',
            'area' => 'dining',
            'guests' => 2,
            'date' => now()->addDay()->toDateString(),
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('restaurant'));

        $this->assertSame(1, ReceptionNotification::where('category', 'booking')->where('title', 'New Restaurant Reservation')->count());
    }

    public function test_a_paid_website_cinema_booking_notifies_the_front_desk(): void
    {
        $movie = Movie::create([
            'title' => 'Test Feature', 'slug' => 'test-feature-recep-notify',
            'genre' => 'Drama', 'duration' => 120, 'rating' => 'PG',
            'adult_price' => 5000, 'child_price' => 3000, 'room_price' => 50000,
            'is_active' => true,
        ]);
        $this->fakeVerify(5375000);

        $this->post('/cinema/book', [
            'reference' => 'recep-cinema-ref-1',
            'movie' => $movie->slug,
            'date' => now()->addDay()->toDateString(),
            'time' => '18:00',
            'room' => 'Room 1',
            'guests' => 2,
            'name' => 'Daniel Ubani',
            'email' => 'daniel@example.test',
            'phone' => '08000000000',
        ])->assertRedirect();

        $this->assertSame(1, ReceptionNotification::where('category', 'booking')->where('title', 'New Cinema Booking')->count());
    }

    public function test_a_guest_issuing_a_visitor_pass_from_their_tablet_notifies_the_front_desk(): void
    {
        $room = Room::create(['name' => 'Alba Suite', 'slug' => 'alba-suite-visitor-notify', 'type' => 'suite', 'price' => 7500]);
        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => '101', 'status' => 'occupied']);
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
            'room_id' => $room->id,
            'room_name' => $room->name,
            'room_unit_id' => $unit->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'nights' => 3,
            'guests' => 2,
            'amount' => 22500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-visitor-notify']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-VISITOR-NOTIFY-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $room->id,
            'room_unit_id' => $unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);
        $token = $device->createToken('tablet')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/visitor-passes', ['visitor_name' => 'Jane Visitor'])
            ->assertCreated();

        $this->assertSame(1, ReceptionNotification::where('category', 'guest')->where('title', 'Visitor Pass Issued')->count());
    }
}
