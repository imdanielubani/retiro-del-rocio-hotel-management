<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\ReceptionNotification;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\SpaCategory;
use App\Models\SpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guest tablet's Spa & Wellness screen: browsing services, charging a
 * session straight to the room, and paying directly via Paystack.
 */
class TabletSpaBookingTest extends TestCase
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
            'slug' => 'alba-suite-spa',
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

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-spa']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-SPA-101',
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

    private function service(array $overrides = []): SpaService
    {
        return SpaService::create(array_merge([
            'name' => 'Signature Deep Tissue Massage',
            'slug' => 'deep-tissue-massage-'.Str::random(6),
            'price' => 35000,
            'duration_minutes' => 90,
            'description' => 'Full-body therapeutic massage.',
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_the_services_feed_lists_active_services_and_the_fixed_time_slots(): void
    {
        $this->service(['name' => 'Active Massage']);
        $this->service(['name' => 'Retired Facial', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/spa/services')
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.name', 'Active Massage')
            ->assertJsonPath('data.services.0.price_label', 'NGN 35,000')
            ->assertJsonPath('data.services.0.duration_label', '90 min')
            ->assertJsonCount(7, 'data.available_times')
            ->assertJsonPath('data.available_times.0', '9:00 AM');
    }

    public function test_the_services_feed_carries_each_services_real_admin_managed_category(): void
    {
        $category = SpaCategory::create([
            'name' => 'Massages', 'slug' => 'massages', 'color' => '#16a34a', 'sort_order' => 1,
        ]);
        $this->service(['name' => 'Categorised Massage', 'spa_category_id' => $category->id]);
        $this->service(['name' => 'Uncategorised Facial']);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/spa/services')
            ->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'Massages')
            ->assertJsonPath('data.categories.0.color', '#16a34a')
            ->assertJsonPath('data.services.0.category', 'Massages')
            ->assertJsonPath('data.services.0.category_color', '#16a34a')
            ->assertJsonPath('data.services.1.category', null)
            ->assertJsonPath('data.services.1.category_color', '#6b7280');
    }

    public function test_charging_a_session_to_the_room_confirms_it_immediately(): void
    {
        $service = $this->service();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/book', [
                'service_slug' => $service->slug,
                'time' => '9:00 AM',
            ])
            ->assertOk()
            ->assertJsonPath('data.service_name', $service->name)
            ->assertJsonPath('data.time', '9:00 AM')
            ->assertJsonPath('data.total_label', 'NGN 37,625') // 35,000 + 7.5% VAT
            ->assertJsonPath('data.payment_method', 'room_charge');

        $booking = SpaBooking::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame(35000, (int) $booking->subtotal);
        $this->assertSame(2625, (int) $booking->vat);

        // Both the guest's own feed and the front desk hear about it.
        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'spa')
            ->assertJsonPath('data.0.title', 'Spa Booking Confirmed');

        $this->assertSame(
            1,
            ReceptionNotification::where('category', 'booking')->where('title', 'New Spa Booking')->count()
        );
    }

    public function test_appointments_lists_confirmed_bookings_newest_first(): void
    {
        SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-APPT-1',
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->subDays(2)->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now()->subDays(2),
        ]);
        SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-APPT-2',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->addDay()->toDateString(),
            'time' => '2:00 PM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/spa/appointments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', 'SPA-APPT-2')
            ->assertJsonPath('data.0.service_name', 'Facial')
            ->assertJsonPath('data.0.time', '2:00 PM')
            ->assertJsonPath('data.0.status_label', 'Confirmed')
            ->assertJsonPath('data.0.payment_method_label', 'Paid Online')
            ->assertJsonPath('data.0.charged_to_room', false)
            ->assertJsonPath('data.0.total_label', 'NGN 16,125')
            ->assertJsonPath('data.1.reference', 'SPA-APPT-1')
            ->assertJsonPath('data.1.payment_method_label', 'Charged to Room')
            ->assertJsonPath('data.1.charged_to_room', true)
            ->assertJsonPath('data.1.total_label', 'NGN 37,625');
    }

    public function test_appointments_excludes_an_abandoned_unpaid_paystack_checkout(): void
    {
        SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-ABANDONED-1',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '2:00 PM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/spa/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_appointments_only_shows_the_active_guests_own_bookings(): void
    {
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
        SpaBooking::create([
            'booking_id' => $otherBooking->id,
            'reference' => 'SPA-FOREIGN-2',
            'services' => [['name' => 'X', 'slug' => 'x', 'price' => 1000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 1000,
            'vat' => 75,
            'total' => 1000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/spa/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_inactive_service_cannot_be_booked(): void
    {
        $service = $this->service(['is_active' => false]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/book', [
                'service_slug' => $service->slug,
                'time' => '9:00 AM',
            ])
            ->assertNotFound();
    }

    public function test_an_invalid_time_slot_is_rejected(): void
    {
        $service = $this->service();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/book', [
                'service_slug' => $service->slug,
                'time' => '4:15 AM',
            ])
            ->assertStatus(422);
    }

    public function test_paying_via_paystack_confirms_the_booking_after_verification(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_spa');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $service = $this->service();

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 3762500, 'channel' => 'card'],
            ], 200),
        ]);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/initialize', [
                'service_slug' => $service->slug,
                'time' => '2:00 PM',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_label', 'NGN 37,625')
            ->json('data.reference');

        // Not yet confirmed — still pending until the charge is verified.
        $this->assertSame('pending', SpaBooking::where('reference', $reference)->first()->payment_status);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.time', '2:00 PM');

        $booking = SpaBooking::where('reference', $reference)->first();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_a_paystack_charge_for_the_wrong_amount_is_rejected(): void
    {
        config()->set('services.paystack.secret_key', 'sk_test_spa');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        $service = $this->service();

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
            ->postJson('/api/v1/tablets/spa/initialize', [
                'service_slug' => $service->slug,
                'time' => '2:00 PM',
            ])
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/confirm', ['reference' => $reference])
            ->assertStatus(402);

        $this->assertSame('pending', SpaBooking::where('reference', $reference)->first()->payment_status);
    }

    public function test_a_guest_cannot_confirm_another_bookings_reference(): void
    {
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
        $foreign = SpaBooking::create([
            'booking_id' => $otherBooking->id,
            'reference' => 'SPA-FOREIGN-1',
            'services' => [['name' => 'X', 'slug' => 'x', 'price' => 1000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 1000,
            'vat' => 75,
            'total' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/spa/confirm', ['reference' => $foreign->reference])
            ->assertNotFound();
    }
}
