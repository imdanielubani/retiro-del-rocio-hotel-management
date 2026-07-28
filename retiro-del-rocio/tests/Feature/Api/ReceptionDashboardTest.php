<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SosAlert;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The reception tablet's front-desk dashboard: today's arrivals and departures,
 * the headline counters, alerts and room status, plus the check-in / check-out
 * actions that drive them.
 */
class ReceptionDashboardTest extends TestCase
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
        $user = User::factory()->create(['status' => 'active', 'name' => 'Daniel Ubani']);
        $user->assignRole('reception');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function otherRoleToken(): string
    {
        Role::findOrCreate('kitchen');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('kitchen');

        return app(JwtService::class)->issue(['sub' => $user->id])['token'];
    }

    private function unit(string $number, string $status = 'available'): RoomUnit
    {
        return RoomUnit::create([
            'room_id' => $this->room->id,
            'number' => $number,
            'status' => $status,
        ]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'reference' => 'BK-'.Str::upper(Str::random(8)),
            'customer_name' => 'Daniel Ubani',
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

    public function test_a_walk_in_arrival_is_flagged_for_the_desk(): void
    {
        $unit = $this->unit('202', 'available');
        $this->booking([
            'customer_name' => 'Walk In Wanda',
            'room_unit_id' => $unit->id,
            'source' => Booking::SOURCE_WALK_IN,
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonPath('data.arrivals.0.guest_name', 'Walk In Wanda')
            ->assertJsonPath('data.arrivals.0.is_walk_in', true)
            ->assertJsonPath('data.arrivals.0.origin_label', 'Walk-in');
    }

    public function test_an_online_arrival_is_not_flagged(): void
    {
        $unit = $this->unit('203', 'available');
        $this->booking(['customer_name' => 'Online Olivia', 'room_unit_id' => $unit->id]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonPath('data.arrivals.0.is_walk_in', false)
            ->assertJsonPath('data.arrivals.0.origin_label', null);
    }

    public function test_the_overview_reports_todays_arrivals_departures_and_counters(): void
    {
        $unit = $this->unit('201', 'available');

        // Arriving today, not yet checked in.
        $this->booking(['customer_name' => 'Ada Lovelace', 'room_unit_id' => $unit->id]);

        // Departing today, currently checked in.
        $out = $this->unit('202', 'occupied');
        $this->booking([
            'customer_name' => 'Grace Hopper',
            'room_unit_id' => $out->id,
            'check_in' => today()->subDays(2)->toDateString(),
            'check_out' => today()->toDateString(),
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(2),
        ]);

        // A verified visitor pass today feeds the fourth counter.
        VisitorPass::create([
            'room_unit_id' => $out->id,
            'visitor_name' => 'Visitor One',
            'code' => '111222',
            'status' => VisitorPass::VERIFIED,
            'verified_at' => now(),
        ]);

        // An open SOS alert feeds the Alerts panel.
        SosAlert::create([
            'room_number' => '104',
            'status' => SosAlert::ACTIVE,
            'raised_at' => now()->subMinutes(5),
        ]);

        $this->unit('203', 'maintenance');

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonPath('data.receptionist.name', 'Daniel Ubani')
            ->assertJsonPath('data.stats.arrivals_today', 1)
            ->assertJsonPath('data.stats.check_ins_today', 0)
            ->assertJsonPath('data.stats.check_outs_today', 0)
            ->assertJsonPath('data.stats.visitor_pass_check_ins', 1)
            ->assertJsonPath('data.stats.overdue_departures', 0)
            ->assertJsonCount(1, 'data.arrivals')
            ->assertJsonPath('data.arrivals.0.guest_name', 'Ada Lovelace')
            ->assertJsonPath('data.arrivals.0.room_label', 'Brisa Residence · Room 201')
            ->assertJsonCount(1, 'data.departures')
            ->assertJsonPath('data.departures.0.guest_name', 'Grace Hopper')
            ->assertJsonCount(1, 'data.alerts')
            ->assertJsonPath('data.alerts.0.severity', 'high')
            ->assertJsonPath('data.alerts.0.type', 'sos')
            ->assertJsonPath('data.room_status.occupied', 1)
            ->assertJsonPath('data.room_status.dirty', 0)
            ->assertJsonPath('data.room_status.maintenance', 1);
    }

    public function test_an_overdue_checked_in_guest_still_appears_in_departures(): void
    {
        // Checked in 5 nights ago, was due to leave 2 days ago, never checked out.
        $unit = $this->unit('204', 'occupied');
        $this->booking([
            'customer_name' => 'Overdue Olu',
            'room_unit_id' => $unit->id,
            'check_in' => today()->subDays(5)->toDateString(),
            'check_out' => today()->subDays(2)->toDateString(),
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(5),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonCount(1, 'data.departures')
            ->assertJsonPath('data.departures.0.guest_name', 'Overdue Olu')
            ->assertJsonPath('data.departures.0.is_overdue', true)
            ->assertJsonPath('data.departures.0.overdue_label', 'Overdue by 2 days')
            ->assertJsonPath('data.stats.overdue_departures', 1)
            // The overdue checkout is also pushed into the Alerts panel, the
            // same way an SOS incident is — not left as a passive list badge.
            ->assertJsonCount(1, 'data.alerts')
            ->assertJsonPath('data.alerts.0.type', 'overdue_departure')
            ->assertJsonPath('data.alerts.0.severity', 'high')
            ->assertJsonPath('data.alerts.0.title', 'Overdue Checkout — Overdue Olu (Brisa Residence · Room 204)')
            ->assertJsonPath('data.alerts.0.time_label', 'Overdue by 2 days');
    }

    public function test_an_sos_alert_sorts_before_an_overdue_departure_alert(): void
    {
        $unit = $this->unit('207', 'occupied');
        $this->booking([
            'customer_name' => 'Overdue Olu',
            'room_unit_id' => $unit->id,
            'check_in' => today()->subDays(5)->toDateString(),
            'check_out' => today()->subDays(2)->toDateString(),
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(5),
        ]);

        SosAlert::create([
            'room_number' => '104',
            'status' => SosAlert::ACTIVE,
            'raised_at' => now()->subMinutes(5),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.alerts')
            ->assertJsonPath('data.alerts.0.type', 'sos')
            ->assertJsonPath('data.alerts.1.type', 'overdue_departure');
    }

    public function test_a_guest_departing_today_is_not_flagged_overdue(): void
    {
        $unit = $this->unit('205', 'occupied');
        $this->booking([
            'customer_name' => 'On Time Tade',
            'room_unit_id' => $unit->id,
            'check_in' => today()->subDays(2)->toDateString(),
            'check_out' => today()->toDateString(),
            'status' => 'checked_in',
            'checked_in_at' => now()->subDays(2),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonCount(1, 'data.departures')
            ->assertJsonPath('data.departures.0.is_overdue', false)
            ->assertJsonPath('data.departures.0.overdue_label', null)
            // Due today is not overdue yet — the stat must not count it.
            ->assertJsonPath('data.stats.overdue_departures', 0);
    }

    public function test_a_guest_not_due_to_leave_yet_does_not_appear_in_departures(): void
    {
        $unit = $this->unit('206', 'occupied');
        $this->booking([
            'customer_name' => 'Future Fola',
            'room_unit_id' => $unit->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(3)->toDateString(),
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonCount(0, 'data.departures');
    }

    public function test_checking_a_guest_in_occupies_the_room_and_records_the_arrival(): void
    {
        $unit = $this->unit('201', 'available');
        $booking = $this->booking(['room_unit_id' => $unit->id]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.room_number', '201')
            ->assertJsonPath('data.guest_name', 'Daniel Ubani')
            ->assertJsonStructure(['data' => ['confirmation', 'check_in_time']]);

        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertNotNull($booking->fresh()->checkin_confirmation);
        $this->assertSame('occupied', $unit->fresh()->status);

        // Now counts as a check-in today. The guest stays on today's arrivals
        // list so it matches the counter, but shown as Checked In rather than
        // still awaiting the desk.
        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.check_ins_today', 1)
            ->assertJsonCount(1, 'data.arrivals')
            ->assertJsonPath('data.arrivals.0.status', 'checked_in')
            ->assertJsonPath('data.arrivals.0.status_label', 'Checked In');
    }

    public function test_todays_lists_show_processed_guests_with_their_status_first_by_action(): void
    {
        // Two arriving today: one still to check in, one already in.
        $u1 = $this->unit('301', 'available');
        $u2 = $this->unit('302', 'occupied');
        $this->booking(['customer_name' => 'Still Waiting', 'room_unit_id' => $u1->id, 'status' => 'paid']);
        $this->booking([
            'customer_name' => 'Already In',
            'room_unit_id' => $u2->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        // Departing today: one already checked out stays on the list.
        $u3 = $this->unit('303', 'available');
        $this->booking([
            'customer_name' => 'Gone Home',
            'room_unit_id' => $u3->id,
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->toDateString(),
            'status' => 'checked_out',
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => now(),
        ]);

        $this->withToken($this->receptionToken())
            ->getJson('/api/v1/reception/overview')
            ->assertOk()
            // Still-to-do sorts first so its action button is at the top.
            ->assertJsonCount(2, 'data.arrivals')
            ->assertJsonPath('data.arrivals.0.guest_name', 'Still Waiting')
            ->assertJsonPath('data.arrivals.0.status', 'paid')
            ->assertJsonPath('data.arrivals.1.guest_name', 'Already In')
            ->assertJsonPath('data.arrivals.1.status', 'checked_in')
            // A completed departure remains, shown as checked out.
            ->assertJsonCount(1, 'data.departures')
            ->assertJsonPath('data.departures.0.status_label', 'Checked Out');
    }

    public function test_check_in_is_idempotent(): void
    {
        $unit = $this->unit('201', 'available');
        $booking = $this->booking(['room_unit_id' => $unit->id]);
        $token = $this->receptionToken();

        $this->withToken($token)->postJson("/api/v1/reception/bookings/{$booking->id}/check-in")->assertOk();
        $first = $booking->fresh()->checkin_confirmation;

        // A second submit returns the same confirmation, not a fresh check-in.
        $this->withToken($token)
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.confirmation', $first);
    }

    public function test_checking_a_guest_out_frees_the_room(): void
    {
        $unit = $this->unit('201', 'occupied');
        $booking = $this->booking([
            'room_unit_id' => $unit->id,
            'status' => 'checked_in',
            'checked_in_at' => now()->subDay(),
        ]);
        $unit->update(['booking_id' => $booking->id]);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_out');

        $this->assertSame('checked_out', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_out_at);
        $this->assertSame('available', $unit->fresh()->status);
        $this->assertNull($unit->fresh()->booking_id);
    }

    public function test_a_booking_not_ready_cannot_be_checked_in(): void
    {
        $booking = $this->booking(['status' => 'pending']);

        $this->withToken($this->receptionToken())
            ->postJson("/api/v1/reception/bookings/{$booking->id}/check-in")
            ->assertStatus(409);
    }

    public function test_a_non_reception_user_is_forbidden(): void
    {
        $this->withToken($this->otherRoleToken())
            ->getJson('/api/v1/reception/overview')
            ->assertForbidden();
    }
}
