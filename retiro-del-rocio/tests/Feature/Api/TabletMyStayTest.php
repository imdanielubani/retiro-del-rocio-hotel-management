<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateTtlockAccess;
use App\Jobs\SendStayExtensionReceipt;
use App\Mail\StayExtensionReceipt;
use App\Models\Booking;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\StayExtensionPayment;
use App\Models\VisitorPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The in-room tablet's My Stay screen and self-service stay extension.
 */
class TabletMyStayTest extends TestCase
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
            'slug' => 'alba-suite-stay',
            'type' => 'suite',
            'price' => 7500,
            'guests' => 4,
            'amenities' => ['High-Speed WiFi', 'Daily Breakfast'],
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
            'guests' => 3,
            'amount' => 37500,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);

        $this->unit->update(['booking_id' => $this->booking->id]);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-stay']);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-STAY-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'room_id' => $this->room->id,
            'room_unit_id' => $this->unit->id,
            'status' => 'online',
            'is_provisioned' => true,
        ]);

        $this->token = $device->createToken('tablet')->plainTextToken;

        // Paystack must look configured so the initialize endpoint runs.
        config()->set('services.paystack.secret_key', 'sk_test_stay');
        config()->set('services.paystack.payment_url', 'https://api.paystack.co');

        // Extending the stay changes the booking dates, which re-issues the gate
        // code after the response. That TTLock work is out of scope here (and has
        // no live lock in tests), so record the queued job rather than run it.
        Bus::fake();
    }

    /** Fake the two Paystack calls: initialize (opens the charge) and verify. */
    private function fakePaystack(int $verifyKobo = 100000000): void
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

    private function initExtension(string $checkOut): array
    {
        return $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => $checkOut])
            ->json('data');
    }

    public function test_my_stay_returns_the_reservation_details(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-stay')
            ->assertOk()
            ->assertJsonPath('data.reservation.room_name', 'Alba Suite')
            ->assertJsonPath('data.reservation.unit_label', 'Room 101')
            ->assertJsonPath('data.reservation.nights', 5)
            ->assertJsonPath('data.reservation.rate_per_night', 7500)
            ->assertJsonPath('data.guests.primary_name', 'Daniel Ubani')
            ->assertJsonPath('data.guests.party_size', 3)
            ->assertJsonPath('data.summary.total_label', 'NGN 37,500')
            // Current Bill is what's charged to the room right now — nothing
            // has been, so it's zero even though the stay itself cost 37,500.
            ->assertJsonPath('data.current_bill_label', 'NGN 0')
            ->assertJsonPath('data.current_bill_due', false)
            ->assertJsonPath('data.inclusions.0.title', 'High-Speed WiFi');
    }

    public function test_stay_summary_itemises_every_extension_however_it_was_paid(): void
    {
        // A Paystack-paid extension and a room-charge one both count toward
        // the stay's full financial history — unlike My Bills, Stay Summary
        // never hides a payment from the guest.
        StayExtensionPayment::create([
            'booking_id' => $this->booking->id,
            'reference' => 'EXT-PAYSTACK-SUMMARY',
            'nights' => 2,
            'new_check_out' => now()->addDays(6)->toDateString(),
            'amount' => 15000,
            'vat' => 1125,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'card',
        ]);
        StayExtensionPayment::create([
            'booking_id' => $this->booking->id,
            'reference' => 'EXT-ROOM-SUMMARY',
            'nights' => 1,
            'new_check_out' => now()->addDays(7)->toDateString(),
            'amount' => 7500,
            'vat' => 563,
            'status' => StayExtensionPayment::SUCCESS,
            'paid_at' => now(),
            'payment_method' => 'room_charge',
        ]);
        $this->booking->update(['amount' => $this->booking->amount + 15000 + 7500]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-stay')
            ->assertOk()
            // Room Rate + Airport Pickup would come first if present; here just
            // Room Rate, then both extensions in order.
            ->assertJsonPath('data.summary.lines.0.label', 'Room Rate')
            ->assertJsonPath('data.summary.lines.0.amount_label', 'NGN 37,500')
            ->assertJsonPath('data.summary.lines.1.label', 'Stay Extension')
            ->assertJsonPath('data.summary.lines.1.amount_label', 'NGN 15,000')
            ->assertJsonPath('data.summary.lines.2.label', 'Stay Extension')
            ->assertJsonPath('data.summary.lines.2.amount_label', 'NGN 7,500')
            // The lines add up to the real total — nothing swallowed, nothing double-counted.
            ->assertJsonPath('data.summary.total_label', 'NGN 60,000')
            // Only the room-charge extension is still outstanding.
            ->assertJsonPath('data.current_bill_label', 'NGN 8,063') // 7,500 + 7.5% VAT
            ->assertJsonPath('data.current_bill_due', true);
    }

    public function test_my_stay_lists_the_invited_visitors(): void
    {
        // An invited (pending) visitor and an arrived (verified) one both show.
        VisitorPass::create([
            'device_id' => null,
            'room_unit_id' => $this->unit->id,
            'booking_id' => $this->booking->id,
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'visitor_name' => 'Maria Lopez',
            'code' => '111111',
            'status' => VisitorPass::PENDING,
        ]);
        VisitorPass::create([
            'device_id' => null,
            'room_unit_id' => $this->unit->id,
            'booking_id' => $this->booking->id,
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'visitor_name' => 'John Doe',
            'code' => '222222',
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now(),
        ]);
        // A denied pass must not appear on the guest's card.
        VisitorPass::create([
            'device_id' => null,
            'room_unit_id' => $this->unit->id,
            'booking_id' => $this->booking->id,
            'host_name' => 'Daniel Ubani',
            'room_number' => '101',
            'suite_name' => 'Alba Suite',
            'visitor_name' => 'Turned Away',
            'code' => '333333',
            'status' => VisitorPass::DENIED,
            'denied_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-stay')
            ->assertOk()
            ->assertJsonCount(2, 'data.visitors')
            ->assertJsonPath('data.visitors.0.name', 'John Doe')      // latest first
            ->assertJsonPath('data.visitors.0.status', 'inside')
            ->assertJsonPath('data.visitors.0.status_label', 'Inside')
            ->assertJsonPath('data.visitors.1.name', 'Maria Lopez')
            ->assertJsonPath('data.visitors.1.status_label', 'Invited');
    }

    public function test_initialize_prices_the_extension_with_vat_and_opens_paystack(): void
    {
        $this->fakePaystack();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => now()->addDays(6)->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.additional_nights', 2)
            ->assertJsonPath('data.subtotal', 15000)   // 2 × 7,500
            ->assertJsonPath('data.vat', 1125)         // 7.5% of 15,000
            ->assertJsonPath('data.total', 16125)
            ->assertJsonPath('data.total_label', 'NGN 16,125')
            ->assertJsonStructure(['data' => ['authorization_url', 'reference', 'callback_url']]);

        $this->assertDatabaseHas('stay_extension_payments', [
            'booking_id' => $this->booking->id,
            'nights' => 2,
            'amount' => 15000, // pre-VAT room charge; VAT (1,125) is stored separately
            'status' => 'pending',
        ]);
    }

    public function test_extending_the_stay_moves_the_checkout_after_the_payment_is_verified(): void
    {
        $this->fakePaystack();
        $newCheckout = now()->addDays(6)->toDateString(); // +2 nights

        $reference = $this->initExtension($newCheckout)['reference'];

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay', ['check_out' => $newCheckout, 'reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.extension.additional_nights', 2)
            ->assertJsonPath('data.reservation.nights', 7);

        $fresh = $this->booking->fresh();
        $this->assertSame($newCheckout, $fresh->check_out->toDateString());
        $this->assertSame(7, (int) $fresh->nights);
        $this->assertSame(52500, (int) $fresh->amount); // 37,500 + 2 × 7,500 (VAT is not on the folio)
        $this->assertDatabaseHas('stay_extension_payments', ['reference' => $reference, 'status' => 'success']);

        // The payment date and channel are stamped for the admin Payments module.
        $payment = StayExtensionPayment::where('reference', $reference)->first();
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('card', $payment->payment_method);

        // The guest is emailed their receipt inline (not after-response, so a
        // failing gate-code re-issue can't skip it).
        Bus::assertDispatchedSync(SendStayExtensionReceipt::class);
    }

    public function test_charging_the_extension_to_room_applies_it_immediately_no_paystack(): void
    {
        $newCheckout = now()->addDays(6)->toDateString(); // +2 nights

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/charge-to-room', ['check_out' => $newCheckout])
            ->assertOk()
            ->assertJsonPath('data.extension.additional_nights', 2)
            ->assertJsonPath('data.extension.additional_cost', 15000) // pre-VAT
            ->assertJsonPath('data.reservation.nights', 7);

        $fresh = $this->booking->fresh();
        $this->assertSame($newCheckout, $fresh->check_out->toDateString());
        $this->assertSame(7, (int) $fresh->nights);
        $this->assertSame(52500, (int) $fresh->amount); // 37,500 + 2 × 7,500

        $payment = StayExtensionPayment::where('booking_id', $this->booking->id)->first();
        $this->assertSame(15000, (int) $payment->amount);
        $this->assertSame(1125, (int) $payment->vat); // 7.5% of 15,000
        $this->assertSame('success', $payment->status);
        $this->assertSame('room_charge', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);

        Bus::assertDispatchedSync(SendStayExtensionReceipt::class);
    }

    public function test_charge_to_room_does_not_overwrite_the_bookings_original_payment_method(): void
    {
        $this->booking->update(['payment_method' => 'bank_transfer']);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/charge-to-room', ['check_out' => now()->addDays(6)->toDateString()])
            ->assertOk();

        $this->assertSame('bank_transfer', $this->booking->fresh()->payment_method);
    }

    public function test_charge_to_room_is_still_blocked_when_the_room_is_booked_by_another_guest(): void
    {
        Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Next Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->addDays(4)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'nights' => 4,
            'guests' => 2,
            'amount' => 30000,
            'status' => 'paid',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/charge-to-room', ['check_out' => now()->addDays(6)->toDateString()])
            ->assertStatus(422);

        $this->assertSame(5, (int) $this->booking->fresh()->nights);
    }

    public function test_the_receipt_is_emailed_even_when_the_gate_reissue_would_fail(): void
    {
        // Reproduces the bug: moving the checkout queues a gate-code re-issue that
        // can throw (offline lock). Stub only that job so it "runs"; the receipt
        // must still reach the guest because it now sends inline, not after-response.
        Bus::fake([GenerateTtlockAccess::class]);
        Mail::fake();
        $this->fakePaystack();
        $this->booking->update(['customer_email' => 'guest@example.test']);

        $newCheckout = now()->addDays(6)->toDateString();
        $reference = $this->initExtension($newCheckout)['reference'];

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay', ['check_out' => $newCheckout, 'reference' => $reference])
            ->assertOk();

        Mail::assertSent(
            StayExtensionReceipt::class,
            fn (StayExtensionReceipt $m) => $m->hasTo('guest@example.test'),
        );
    }

    public function test_verifying_the_same_reference_twice_is_idempotent(): void
    {
        $this->fakePaystack();
        $newCheckout = now()->addDays(6)->toDateString();
        $reference = $this->initExtension($newCheckout)['reference'];

        $payload = ['check_out' => $newCheckout, 'reference' => $reference];
        $this->withToken($this->token)->postJson('/api/v1/tablets/extend-stay', $payload)->assertOk();
        $this->withToken($this->token)->postJson('/api/v1/tablets/extend-stay', $payload)->assertOk();

        // The extension was applied exactly once.
        $this->assertSame(7, (int) $this->booking->fresh()->nights);
        // And the receipt emailed exactly once, not on the repeated verify.
        Bus::assertDispatchedSyncTimes(SendStayExtensionReceipt::class, 1);
    }

    public function test_extension_is_not_applied_when_the_payment_did_not_succeed(): void
    {
        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'reference' => 'ref'],
            ], 200),
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'abandoned', 'amount' => 0],
            ], 200),
        ]);

        $newCheckout = now()->addDays(6)->toDateString();
        $reference = $this->initExtension($newCheckout)['reference'];

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay', ['check_out' => $newCheckout, 'reference' => $reference])
            ->assertStatus(402);

        $this->assertSame(5, (int) $this->booking->fresh()->nights);
        // No charge, no receipt.
        Bus::assertNotDispatchedSync(SendStayExtensionReceipt::class);
    }

    public function test_a_new_checkout_before_the_current_one_is_rejected(): void
    {
        $this->fakePaystack();

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => now()->addDays(2)->toDateString()])
            ->assertStatus(422);

        $this->assertSame(5, (int) $this->booking->fresh()->nights);
    }

    public function test_extension_is_blocked_when_the_room_is_booked_by_another_guest(): void
    {
        $this->fakePaystack();

        // Another guest already holds this unit right after the current checkout.
        Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Next Guest',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->addDays(4)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'nights' => 4,
            'guests' => 2,
            'amount' => 30000,
            'status' => 'paid',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/tablets/extend-stay/initialize', ['check_out' => now()->addDays(6)->toDateString()])
            ->assertStatus(422);

        $this->assertSame(5, (int) $this->booking->fresh()->nights);
    }

    public function test_my_stay_requires_a_checked_in_guest(): void
    {
        $this->booking->update(['status' => 'checked_out']);
        $this->unit->update(['status' => 'available']);

        $this->withToken($this->token)
            ->getJson('/api/v1/tablets/my-stay')
            ->assertStatus(404);
    }
}
