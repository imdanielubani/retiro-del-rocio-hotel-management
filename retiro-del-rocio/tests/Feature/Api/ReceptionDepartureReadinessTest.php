<?php

namespace Tests\Feature\Api;

use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\HousekeepingRequest;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\User;
use App\Models\VisitorPass;
use App\Models\WorkOrder;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's pre-checkout readiness check: the outstanding
 * balance (a hard block), the guest's still-open housekeeping requests and
 * maintenance faults (a heads-up, not a block), and any visitor passes still
 * active against the stay (informational — checkout closes them
 * automatically).
 */
class ReceptionDepartureReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-departure',
            'type' => 'suite',
            'price' => 150000,
        ]);
        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '301',
            'status' => 'occupied',
        ]);
    }

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function booking(): Booking
    {
        $booking = Booking::create([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Ada Lovelace',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => today()->subDays(2)->toDateString(),
            'check_out' => today()->toDateString(),
            'nights' => 2,
            'guests' => 2,
            'amount' => 300000,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(2),
        ]);
        $this->unit->update(['booking_id' => $booking->id]);

        return $booking;
    }

    public function test_a_clean_departure_has_nothing_open_but_still_needs_inspection(): void
    {
        $booking = $this->booking();

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.due', 0)
            ->assertJsonPath('data.inspection_status', 'not_requested')
            // No balance owed, but nobody has inspected the room yet.
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonCount(0, 'data.open_requests')
            ->assertJsonCount(0, 'data.open_work_orders')
            ->assertJsonCount(0, 'data.active_visitor_passes');
    }

    public function test_an_open_housekeeping_request_shows_as_a_heads_up(): void
    {
        $booking = $this->booking();
        HousekeepingRequest::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'type' => 'towels',
        ]);
        // A completed one shouldn't clutter the readiness check.
        $done = HousekeepingRequest::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'type' => 'amenities',
        ]);
        $done->complete();

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonCount(1, 'data.open_requests')
            ->assertJsonPath('data.open_requests.0.type_label', 'Towels');
    }

    public function test_a_checkout_inspection_request_never_shows_as_a_heads_up_item(): void
    {
        $booking = $this->booking();
        HousekeepingRequest::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'type' => HousekeepingRequest::CHECKOUT_INSPECTION,
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.inspection_status', 'pending')
            ->assertJsonCount(0, 'data.open_requests');
    }

    public function test_requesting_an_inspection_notifies_housekeeping_and_can_be_completed(): void
    {
        $booking = $this->booking();

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/request-inspection")
            ->assertOk()
            ->assertJsonPath('data.inspection_status', 'pending');

        $this->assertDatabaseHas('housekeeping_requests', [
            'booking_id' => $booking->id,
            'room_unit_id' => $this->unit->id,
            'type' => HousekeepingRequest::CHECKOUT_INSPECTION,
            'status' => HousekeepingRequest::PENDING,
        ]);
        $this->assertDatabaseHas('housekeeping_notifications', [
            'title' => 'Checkout Inspection Needed',
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.inspection_status', 'pending')
            ->assertJsonPath('data.can_check_out', false);

        HousekeepingRequest::where('booking_id', $booking->id)
            ->where('type', HousekeepingRequest::CHECKOUT_INSPECTION)
            ->firstOrFail()
            ->complete();

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.inspection_status', 'completed')
            ->assertJsonPath('data.can_check_out', true);
    }

    public function test_requesting_an_inspection_twice_does_not_raise_a_duplicate(): void
    {
        $booking = $this->booking();

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/request-inspection")
            ->assertOk();
        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/request-inspection")
            ->assertOk();

        $this->assertSame(
            1,
            HousekeepingRequest::where('booking_id', $booking->id)
                ->where('type', HousekeepingRequest::CHECKOUT_INSPECTION)
                ->count(),
        );
    }

    public function test_an_open_maintenance_fault_shows_as_a_heads_up(): void
    {
        $booking = $this->booking();
        WorkOrder::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'title' => 'AC not cooling',
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonCount(1, 'data.open_work_orders')
            ->assertJsonPath('data.open_work_orders.0.title', 'AC not cooling');
    }

    public function test_an_active_visitor_pass_is_shown_but_does_not_block(): void
    {
        $booking = $this->booking();
        VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'visitor_name' => 'Michael Brown',
            'code' => '123456',
            'status' => VisitorPass::VERIFIED,
        ]);
        // Already exited — not "active" any more.
        VisitorPass::create([
            'room_unit_id' => $this->unit->id,
            'booking_id' => $booking->id,
            'visitor_name' => 'Exited Visitor',
            'code' => '654321',
            'status' => VisitorPass::VERIFIED,
            'exited_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonCount(1, 'data.active_visitor_passes')
            ->assertJsonPath('data.active_visitor_passes.0.visitor_name', 'Michael Brown');
    }

    public function test_an_outstanding_balance_blocks_checkout(): void
    {
        $booking = $this->booking();
        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-READY-1',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.can_check_out', false)
            ->assertJsonPath('data.due', 16125);
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $booking = $this->booking();
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertForbidden();
    }

    public function test_the_desk_can_settle_a_balance_the_guest_already_paid_in_person(): void
    {
        $booking = $this->booking();
        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-SETTLE-1',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill", ['payment_method' => 'cash'])
            ->assertOk()
            ->assertJsonPath('data.due', 0);

        // The balance is clear, but checkout still waits on housekeeping's
        // inspection — settling the bill alone never unlocks it.
        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/departure-readiness")
            ->assertOk()
            ->assertJsonPath('data.due', 0)
            ->assertJsonPath('data.can_check_out', false);

        $this->assertDatabaseHas('bill_payments', [
            'booking_id' => $booking->id,
            'amount' => 15000,
            'vat' => 1125,
            'status' => BillPayment::SUCCESS,
            'payment_method' => 'cash',
        ]);
    }

    public function test_settling_the_bill_also_marks_the_spa_booking_itself_paid(): void
    {
        $booking = $this->booking();
        // Charged to the room last month, still genuinely pending — the
        // real shape of a stale unsettled charge, not the fixture's
        // pre-flipped "paid" shortcut used by the other settle-bill test.
        $spa = SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-STALE-1',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->subMonth()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill", ['payment_method' => 'bank_transfer'])
            ->assertOk()
            ->assertJsonPath('data.due', 0);

        // Not still "pending" dated last month — the admin Payments ledger
        // reads this column directly, not the BillPayment total, so it has
        // to flip too, dated today (when it was actually settled).
        $spa->refresh();
        $this->assertSame('paid', $spa->payment_status);
        $this->assertNotNull($spa->paid_at);
        $this->assertTrue($spa->paid_at->isToday());
    }

    public function test_settling_a_balance_requires_a_payment_method(): void
    {
        $booking = $this->booking();
        SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-SETTLE-NOMETHOD',
            'services' => [['name' => 'Facial', 'slug' => 'facial', 'price' => 15000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '10:30 AM',
            'subtotal' => 15000,
            'vat' => 1125,
            'total' => 15000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'room_charge',
        ]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill")
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill", ['payment_method' => 'not-a-real-method'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_settling_an_already_clear_balance_is_a_no_op(): void
    {
        $booking = $this->booking();

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill")
            ->assertOk()
            ->assertJsonPath('data.due', 0);

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_a_non_reception_user_cannot_settle_a_bill(): void
    {
        $booking = $this->booking();
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->postJson("/api/v1/reception/bookings/{$booking->id}/settle-bill")
            ->assertForbidden();
    }

    public function test_a_non_reception_user_cannot_request_an_inspection(): void
    {
        $booking = $this->booking();
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->postJson("/api/v1/reception/bookings/{$booking->id}/request-inspection")
            ->assertForbidden();
    }
}
