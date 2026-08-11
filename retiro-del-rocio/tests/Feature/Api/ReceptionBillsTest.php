<?php

namespace Tests\Feature\Api;

use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's Bills module: every checked-in guest's outstanding
 * room-charge balance, and a single guest's itemised folio.
 *
 * The room rate is always settled at booking time — it's shown on the bill
 * for reference but never counts toward what's still due. Only what's
 * charged to the room during the stay (a self-service extension or a spa
 * session picked "Charge to Room") is real outstanding balance.
 */
class ReceptionBillsTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private RoomUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::create([
            'name' => 'Brisa Residence',
            'slug' => 'brisa-residence-bills',
            'type' => 'suite',
            'price' => 4250,
        ]);

        $this->unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => '204',
            'status' => 'occupied',
        ]);
    }

    private function receptionToken(): string
    {
        Role::findOrCreate('reception');
        $user = User::factory()->create(['status' => 'active', 'name' => 'Front Desk']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function checkedInBooking(array $overrides = []): Booking
    {
        $booking = Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Ada Lovelace',
            'room_id' => $this->room->id,
            'room_name' => $this->room->name,
            'room_unit_id' => $this->unit->id,
            'check_in' => now()->subDays(1)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 5,
            'guests' => 2,
            'amount' => 21250,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ], $overrides));

        $this->unit->update(['booking_id' => $booking->id]);

        return $booking;
    }

    private function roomChargeSpa(Booking $booking): SpaBooking
    {
        return SpaBooking::create([
            'booking_id' => $booking->id,
            'reference' => 'SPA-TEST-'.Str::upper(Str::random(8)),
            'services' => [['name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue', 'price' => 35000, 'qty' => 1]],
            'guests' => 1,
            'date' => now()->toDateString(),
            'time' => '9:00 AM',
            'subtotal' => 35000,
            'vat' => 2625,
            'total' => 35000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'room_charge',
            'paid_at' => now(),
        ]);
    }

    public function test_bills_lists_every_checked_in_booking_with_its_balance(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills')
            ->assertOk()
            ->assertJsonPath('data.bills.0.booking_id', $booking->id)
            ->assertJsonPath('data.bills.0.guest_name', 'Ada Lovelace')
            ->assertJsonPath('data.bills.0.room_label', 'Room 204')
            // Spa subtotal (35,000) + its VAT (2,625) — the room charge is not due.
            ->assertJsonPath('data.bills.0.total_due', 37625)
            ->assertJsonPath('data.bills.0.total_due_label', 'NGN 37,625')
            ->assertJsonPath('data.bills.0.has_balance', true)
            ->assertJsonPath('data.summary.total_outstanding', 37625)
            ->assertJsonPath('data.summary.guests_with_balance', 1);
    }

    public function test_a_room_with_only_the_room_rate_has_nothing_due(): void
    {
        $this->checkedInBooking();

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills')
            ->assertOk()
            ->assertJsonPath('data.bills.0.total_due', 0)
            ->assertJsonPath('data.bills.0.has_balance', false)
            ->assertJsonPath('data.summary.guests_with_balance', 0);
    }

    public function test_a_checked_out_booking_does_not_appear(): void
    {
        $this->checkedInBooking(['status' => 'checked_out']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills')
            ->assertOk()
            ->assertJsonCount(0, 'data.bills');
    }

    public function test_guests_with_a_balance_sort_first(): void
    {
        $settled = $this->checkedInBooking(['customer_name' => 'Grace Hopper']);
        $this->roomChargeSpa($settled);
        BillPayment::create([
            'booking_id' => $settled->id,
            'reference' => 'BILL-SETTLED-1',
            'amount' => 35000,
            'vat' => 2625,
            'status' => BillPayment::SUCCESS,
            'paid_at' => now(),
        ]);
        $owing = $this->checkedInBooking(['customer_name' => 'Alan Turing']);
        $this->roomChargeSpa($owing);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills')
            ->assertOk()
            ->assertJsonPath('data.bills.0.booking_id', $owing->id)
            ->assertJsonPath('data.bills.0.has_balance', true)
            ->assertJsonPath('data.bills.1.booking_id', $settled->id)
            ->assertJsonPath('data.bills.1.has_balance', false)
            ->assertJsonPath('data.summary.guests_with_balance', 1);
    }

    public function test_search_narrows_by_guest_name_or_room(): void
    {
        $this->checkedInBooking(['customer_name' => 'Ada Lovelace']);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills?search=Ada')
            ->assertOk()
            ->assertJsonCount(1, 'data.bills');

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/bills?search=Nobody')
            ->assertOk()
            ->assertJsonCount(0, 'data.bills');
    }

    public function test_booking_bill_returns_the_itemised_folio(): void
    {
        $booking = $this->checkedInBooking();
        $this->roomChargeSpa($booking);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/bill")
            ->assertOk()
            ->assertJsonPath('data.reservation.guest_name', 'Ada Lovelace')
            ->assertJsonPath('data.reservation.room_name', 'Brisa Residence')
            ->assertJsonPath('data.reservation.unit_label', 'Room 204')
            ->assertJsonPath('data.categories.0.key', 'room')
            ->assertJsonPath('data.categories.1.key', 'spa')
            ->assertJsonPath('data.categories.1.has_charges', true)
            ->assertJsonPath('data.categories.1.items.0.label', 'Deep Tissue Massage')
            ->assertJsonPath('data.summary.total_due', 37625);
    }

    public function test_booking_bill_is_not_available_for_a_booking_that_is_not_checked_in(): void
    {
        $booking = $this->checkedInBooking(['status' => 'paid']);

        $this->withToken($this->receptionToken())
            ->getJson("/api/v1/reception/bookings/{$booking->id}/bill")
            ->assertStatus(404);
    }

    public function test_bills_requires_the_reception_role(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = app(JwtService::class)->issue(['sub' => $user->id])['token'];

        $this->withToken($token)
            ->getJson('/api/v1/reception/bills')
            ->assertStatus(403);
    }
}
