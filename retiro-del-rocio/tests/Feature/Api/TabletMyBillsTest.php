<?php

namespace Tests\Feature\Api;

use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\CinemaBooking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\StayExtensionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The in-room tablet's My Bills screen and self-service bill pre-settlement.
 *
 * The room rate and airport pickup are settled at booking time and never
 * appear on this bill at all. Only what's actually charged to the room
 * during the stay (a self-service extension or a spa session picked
 * "Charge to Room") shows up here.
 */
class TabletMyBillsTest extends TestCase
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
            'slug' => 'alba-suite-bills',
            'type' => 'suite',
            'price' => 4250,
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
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 21250, // 5 × 4,250 — paid at booking time
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-bills']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-BILLS-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $device->createToken('tablet')->plainTextToken;

        config()->set('services.paystack.secret_key', 'sk_test_bills');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');
    }

    private function fakePaystack(int $verifyKobo): void
    {
        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => $verifyKobo, 'channel' => 'card'],
            ], 200),
        ]);
    }

    private function roomChargeSpa(): SpaBooking
    {
        return SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-TEST-1',
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625, // 7.5%
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);
    }

    public function test_my_bills_with_no_room_charges_shows_no_charges_anywhere(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.reservation.room_name', 'Alba Suite')
            ->assertJsonPath('data.reservation.unit_label', 'Room 101')
            // The room rate was paid at booking — it never appears on this bill.
            ->assertJsonPath('data.categories.0.key', 'room')
            ->assertJsonPath('data.categories.0.has_charges', false)
            ->assertJsonCount(0, 'data.categories.0.items')
            ->assertJsonPath('data.categories.1.key', 'spa')
            ->assertJsonPath('data.categories.1.has_charges', false)
            ->assertJsonPath('data.categories.2.key', 'restaurant')
            ->assertJsonPath('data.categories.2.has_charges', false)
            ->assertJsonPath('data.summary.total_due', 0)
            ->assertJsonPath('data.summary.total_due_label', 'NGN 0')
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_a_room_charge_spa_booking_is_the_outstanding_balance(): void
    {
        $this->roomChargeSpa();

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.categories.1.key', 'spa')
            ->assertJsonPath('data.categories.1.has_charges', true)
            ->assertJsonPath('data.categories.1.amount_label', 'NGN 35,000')
            ->assertJsonPath('data.categories.1.items.0.label', 'Deep Tissue Massage')
            // Spa subtotal (35,000) + its VAT (2,625) — the room charge is not due.
            ->assertJsonPath('data.summary.total_due', 37625)
            ->assertJsonPath('data.can_pay', true);
    }

    public function test_a_paystack_paid_stay_extension_never_appears_on_the_bill(): void
    {
        // The guest already paid for this extension via Paystack when they
        // booked it — it must not surface here at all, as a line item or in
        // any total, the same way a Paystack-paid spa session doesn't.
        StayExtensionPayment::create([
            'booking_id' => $this->booking->id,
            'reference' => 'EXT-PAYSTACK-1',
            'nights' => 2,
            'new_check_out' => now()->addDays(6)->toDateString(),
            'amount' => 8500, // pre-VAT
            'vat' => 638,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'card',
        ]);
        $this->booking->update(['amount' => $this->booking->amount + 8500]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            // Room Charges has nothing — the Paystack-paid extension leaves no
            // trace here at all, the same as a Paystack-paid spa session.
            ->assertJsonCount(0, 'data.categories.0.items')
            ->assertJsonPath('data.categories.0.has_charges', false)
            ->assertJsonMissing(['label' => 'Stay Extension'])
            ->assertJsonPath('data.summary.total_due', 0)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_a_room_charge_extension_appears_but_a_paystack_one_does_not(): void
    {
        StayExtensionPayment::create([
            'booking_id' => $this->booking->id,
            'reference' => 'EXT-ROOM-1',
            'nights' => 1,
            'new_check_out' => now()->addDays(5)->toDateString(),
            'amount' => 4250,
            'vat' => 319,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'room_charge',
        ]);
        StayExtensionPayment::create([
            'booking_id' => $this->booking->id,
            'reference' => 'EXT-PAYSTACK-2',
            'nights' => 2,
            'new_check_out' => now()->addDays(7)->toDateString(),
            'amount' => 8500,
            'vat' => 638,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'bank',
        ]);
        $this->booking->update(['amount' => $this->booking->amount + 4250 + 8500]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            // Exactly one Stay Extension line — the room-charge one only.
            ->assertJsonCount(1, 'data.categories.0.items')
            ->assertJsonPath('data.categories.0.items.0.label', 'Stay Extension')
            ->assertJsonPath('data.categories.0.items.0.amount_label', 'NGN 4,250')
            // Room Charges total: the room-charge extension only.
            ->assertJsonPath('data.categories.0.amount_label', 'NGN 4,250')
            ->assertJsonPath('data.summary.total_due', 4569); // 4,250 + 319 VAT

        $response->assertJsonMissing(['sub' => '2 night(s) to '.now()->addDays(7)->format('M j, Y')]);
    }

    private function roomChargeCinema(): CinemaBooking
    {
        return CinemaBooking::create([
            'booking_id' => $this->booking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-TEST-1',
            'movie_title' => 'The Great Escape',
            'show_date' => now()->toDateString(),
            'show_time' => '2:00 PM',
            'room' => 'Room 1',
            'guests' => 2,
            'snacks' => [],
            'subtotal' => 40000,
            'vat' => 3000, // 7.5%
            'amount' => 40000,
            'customer_name' => 'Daniel Ubani',
            'customer_email' => 'daniel@example.test',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);
    }

    public function test_a_room_charge_cinema_booking_is_the_outstanding_balance(): void
    {
        $this->roomChargeCinema();

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.categories.3.key', 'cinema')
            ->assertJsonPath('data.categories.3.has_charges', true)
            ->assertJsonPath('data.categories.3.amount_label', 'NGN 40,000')
            ->assertJsonPath('data.categories.3.items.0.label', 'The Great Escape')
            // Cinema subtotal (40,000) + its VAT (3,000) — the room charge is not due.
            ->assertJsonPath('data.summary.total_due', 43000)
            ->assertJsonPath('data.can_pay', true);
    }

    public function test_a_paystack_cinema_booking_not_charged_to_the_room_is_excluded(): void
    {
        CinemaBooking::create([
            'booking_id' => $this->booking->id,
            'code' => CinemaBooking::makeCode(),
            'reference' => 'CIN-TEST-2',
            'movie_title' => 'The Great Escape',
            'show_date' => now()->toDateString(),
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
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.categories.3.has_charges', false)
            ->assertJsonPath('data.summary.total_due', 0)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_a_paystack_spa_booking_not_charged_to_the_room_is_excluded(): void
    {
        SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-TEST-2',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'paid_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.categories.1.has_charges', false)
            ->assertJsonPath('data.summary.total_due', 0)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_initialize_prices_the_full_outstanding_balance_and_opens_paystack(): void
    {
        $this->roomChargeSpa();
        $this->fakePaystack(3762500);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/initialize')
            ->assertOk()
            ->assertJsonPath('data.total', 37625)
            ->assertJsonPath('data.total_label', 'NGN 37,625')
            ->assertJsonStructure(['data' => ['authorization_url', 'reference', 'callback_url']]);

        $this->assertDatabaseHas('bill_payments', [
            'booking_id' => $this->booking->id,
            'amount' => 35000,
            'vat' => 2625,
            'status' => 'pending',
        ]);
    }

    public function test_confirming_the_payment_settles_the_balance_and_shows_up_as_already_paid(): void
    {
        $this->roomChargeSpa();
        $this->fakePaystack(3762500);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/initialize')
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/confirm', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.total_label', 'NGN 37,625');

        $this->assertDatabaseHas('bill_payments', ['reference' => $reference, 'status' => 'success']);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertOk()
            ->assertJsonPath('data.summary.total_due', 0)
            ->assertJsonPath('data.can_pay', false);
    }

    public function test_confirming_the_payment_also_marks_the_spa_booking_itself_paid(): void
    {
        // A genuinely still-outstanding room-charge spa session — pending,
        // not yet paid, the way one actually looks before settlement.
        $spa = SpaBooking::create([
            'booking_id' => $this->booking->id,
            'reference' => 'SPA-PENDING-1',
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->subDays(20)->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);
        $this->fakePaystack(3762500);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/initialize')
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/confirm', ['reference' => $reference])
            ->assertOk();

        // Not still "pending" dated the day it was charged — the admin
        // Payments ledger reads this column directly, not the BillPayment
        // total, so it has to flip too, dated today.
        $spa->refresh();
        $this->assertSame('paid', $spa->payment_status);
        $this->assertNotNull($spa->paid_at);
        $this->assertTrue($spa->paid_at->isToday());
    }

    public function test_verifying_the_same_reference_twice_is_idempotent(): void
    {
        $this->roomChargeSpa();
        $this->fakePaystack(3762500);

        $reference = $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/initialize')
            ->json('data.reference');

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/confirm', ['reference' => $reference])
            ->assertOk();
        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/confirm', ['reference' => $reference])
            ->assertOk();

        $this->assertSame(1, BillPayment::where('reference', $reference)->where('status', 'success')->count());
    }

    public function test_initialize_is_rejected_when_there_is_nothing_to_pay(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/my-bills/initialize')
            ->assertStatus(422);
    }

    public function test_my_bills_requires_a_checked_in_guest(): void
    {
        $this->booking->update(['status' => 'checked_out']);
        $this->unit->update(['status' => 'available']);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-bills')
            ->assertStatus(404);
    }
}
