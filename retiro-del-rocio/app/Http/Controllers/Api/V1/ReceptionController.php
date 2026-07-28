<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\RoomUnit;
use App\Models\SosAlert;
use App\Models\User;
use App\Models\VisitorPass;
use App\Services\CloudinaryService;
use App\Services\VisitorPassProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reception tablet dashboard (Figma 299:322 / 345:14699). Authenticated by the
 * receptionist's staff JWT — the JWT middleware binds the user, and every
 * endpoint re-checks the `reception` role so a token from another station can't
 * work the front desk.
 *
 * Hotel-wide, like the security dashboard: reception watches every room, not one.
 */
class ReceptionController extends Controller
{
    /**
     * GET /reception/overview — everything the dashboard renders in one call: the
     * headline counters, today's arrivals still to be checked in, departures due
     * today or overdue (still checked in, whatever their checkout date), open
     * alerts and the room-status tally.
     */
    public function overview(Request $request): JsonResponse
    {
        $receptionist = $this->receptionist($request);

        $today = today();

        // Everyone arriving / departing today, so the lists match their counters
        // rather than emptying out as the desk works through them. Those still to
        // be handled sort first (and carry the action button on the tablet);
        // already-processed ones stay on the list showing their status.
        $arrivals = Booking::with('roomUnit')
            ->whereDate('check_in', $today)
            ->whereIn('status', ['paid', 'checked_in'])
            ->orderByRaw("CASE status WHEN 'paid' THEN 0 ELSE 1 END")
            ->orderBy('check_in')
            ->get();

        // A checked-in guest never disappears from this list just because the
        // calendar rolled past their checkout date — an overdue departure is
        // still a departure the desk must act on, and often the more urgent one.
        // Already-checked-out guests stay scoped to today, so the list doesn't
        // balloon with all-time history.
        $departures = Booking::with('roomUnit')
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('status', 'checked_in')->whereDate('check_out', '<=', $today))
                ->orWhere(fn ($q2) => $q2->where('status', 'checked_out')->whereDate('check_out', $today)))
            ->orderByRaw("CASE status WHEN 'checked_in' THEN 0 ELSE 1 END")
            ->orderBy('check_out')
            ->get();

        // Alerts is fed from the live SOS incidents reception should be aware of;
        // an empty list simply renders the panel's empty state. The same open
        // incidents also drive the priority SOS overlay and the dashboard's live
        // awareness, so they are shipped in their full incident shape too.
        $incidents = SosAlert::open()
            ->with('acknowledgedBy:id,name')
            ->latest('raised_at')
            ->limit(20)
            ->get();

        // Overdue checkouts are pushed into the same Alerts panel as SOS, not
        // left as a passive badge on the departures list — the desk shouldn't
        // have to notice a stale date on their own. Reused straight off
        // $departures (already the right rows, already sorted worst-first) so
        // there's no extra query. SOS always sorts first — a real emergency
        // outranks an operational reminder.
        $overdueBookings = $departures->filter(
            fn (Booking $booking) => $booking->status === 'checked_in' && $booking->overdueDays() > 0
        );

        return response()->json(['data' => [
            'receptionist' => [
                'name' => $receptionist->name,
                'role' => 'Reception',
            ],
            'stats' => [
                // Everyone scheduled to arrive today, whether or not they are in yet.
                'arrivals_today' => Booking::whereDate('check_in', $today)
                    ->whereIn('status', ['paid', 'checked_in'])->count(),
                // Actions actually completed today, keyed off the recorded moment.
                'check_ins_today' => Booking::whereDate('checked_in_at', $today)->count(),
                'check_outs_today' => Booking::whereDate('checked_out_at', $today)->count(),
                // Visitors admitted at the gate today (verified passes).
                'visitor_pass_check_ins' => VisitorPass::where('status', VisitorPass::VERIFIED)
                    ->whereDate('verified_at', $today)->count(),
                // Still checked in with a checkout date already in the past —
                // strictly before today, so a guest merely due out today isn't
                // counted as overdue yet.
                'overdue_departures' => Booking::where('status', 'checked_in')
                    ->whereDate('check_out', '<', $today)->count(),
            ],
            'arrivals' => $arrivals->map->toReceptionArrivalArray()->values(),
            'departures' => $departures->map->toReceptionDepartureArray()->values(),
            'alerts' => $incidents->map->toReceptionAlertArray()
                ->concat($overdueBookings->map->toReceptionAlertArray())
                ->values(),
            'incidents' => $incidents->map->toSecurityArray()->values(),
            'room_status' => [
                'occupied' => RoomUnit::where('status', 'occupied')->count(),
                // Housekeeping "dirty" is not a tracked room state yet; shown for
                // the design's tile, always zero until the status exists.
                'dirty' => 0,
                'maintenance' => RoomUnit::where('status', 'maintenance')->count(),
            ],
        ]]);
    }

    /**
     * GET /reception/bookings/{booking}/rooms — the room-assignment step.
     *
     * Returns the unit the booking is (or will be) assigned to, plus every other
     * free unit in the hotel the desk can move the guest to — grouped by room so
     * the whole available inventory is visible, not just the booked room type.
     * Moving to a different room type does not reprice the stay: the booking's
     * charge is left unchanged (a separate admin/billing decision).
     */
    public function roomOptions(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);

        $assigned = $booking->room_unit_id
            ? RoomUnit::with('devices')->find($booking->room_unit_id)
            : $booking->autoAssignRoomUnit()?->loadMissing('devices');

        $available = RoomUnit::where('status', 'available')
            ->when($booking->room_unit_id, fn ($q) => $q->where('id', '!=', $booking->room_unit_id))
            ->with(['room', 'devices'])
            ->orderBy('room_id')
            ->orderByRaw('LENGTH(number), number')
            ->get();

        return response()->json(['data' => [
            'assigned' => $assigned ? $this->unitOption($assigned, $booking) : null,
            'available' => $available->map(fn ($u) => $this->unitOption($u, $booking))->values(),
        ]]);
    }

    private function unitOption(RoomUnit $unit, Booking $booking): array
    {
        $room = $unit->relationLoaded('room') ? $unit->room : $unit->room()->first();
        $room ??= $booking->room;

        // Whether this unit has an in-room tablet — mirrors the admin dashboard's
        // check-in step, so the desk knows the guest's details will appear on the
        // room's device the moment they are admitted.
        $hasTablet = ($unit->relationLoaded('devices') ? $unit->devices : $unit->devices())
            ->count() > 0;

        return [
            'id' => $unit->id,
            'number' => $unit->number,
            'room_name' => $room?->name ?? $booking->room_name,
            'price_label' => $room ? 'NGN '.number_format((int) $room->price).'/night' : null,
            'has_tablet' => $hasTablet,
        ];
    }

    /**
     * POST /reception/bookings/{booking}/check-in — admit an arriving guest.
     *
     * Carries the identity captured on the tablet: the document type and number,
     * an optional scan (uploaded to Cloudinary here, so its credentials stay
     * server-side and the admin can view the file later), and the room unit the
     * desk chose. Occupying the room and checking the booking in are one act — an
     * error between them would leave a room occupied by a guest who was never
     * checked in. Idempotent: a second submit returns the existing confirmation.
     */
    public function checkIn(Request $request, Booking $booking, CloudinaryService $cloudinary): JsonResponse
    {
        $this->receptionist($request);

        if ($booking->status === 'checked_in') {
            return response()->json(['data' => $booking->fresh()->toCheckinConfirmationArray()]);
        }

        abort_unless($booking->status === 'paid', 409, 'This booking is not ready for check-in.');

        $data = $request->validate([
            'document_type' => ['nullable', 'in:passport,nin,work_id,drivers_license,voter_card'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'room_unit_id' => ['nullable', 'integer'],
        ]);

        // The chosen room must be a free unit (or the one already held for this
        // booking) — never someone else's occupied room. Any room type is
        // allowed: the desk can place the guest anywhere that is open.
        $unit = null;
        if (! empty($data['room_unit_id'])) {
            $unit = RoomUnit::where('id', $data['room_unit_id'])
                ->where(fn ($q) => $q->where('status', 'available')->orWhere('id', $booking->room_unit_id))
                ->with('room')
                ->first();
            abort_unless($unit, 422, 'That room is not available.');
        }

        // Upload the scan before the transaction — a network call has no place
        // inside a DB lock, and a failed upload must not fail the check-in.
        $upload = $request->hasFile('document')
            ? $cloudinary->upload($request->file('document'))
            : null;

        DB::transaction(function () use ($booking, $unit, $data, $upload) {
            $target = $unit ?? $booking->autoAssignRoomUnit();
            if ($target) {
                // Free any previously held unit before occupying the new one.
                if ($booking->room_unit_id && $booking->room_unit_id !== $target->id) {
                    RoomUnit::release($booking->room_unit_id);
                }
                $target->update(['status' => 'occupied', 'booking_id' => $booking->id]);
                $booking->room_unit_id = $target->id;

                // If the guest was moved to a room of a different type, keep the
                // booking's room reference consistent. The charge is deliberately
                // left unchanged — repricing is a separate admin/billing decision.
                $targetRoom = $target->relationLoaded('room') ? $target->room : $target->room()->first();
                if ($targetRoom && $targetRoom->id !== $booking->room_id) {
                    $booking->room_id = $targetRoom->id;
                    $booking->room_name = $targetRoom->name;
                }
            }

            $booking->forceFill([
                'id_document_type' => $data['document_type'] ?? $booking->id_document_type,
                'id_document_number' => $data['document_number'] ?? $booking->id_document_number,
                'id_document_public_id' => $upload['public_id'] ?? $booking->id_document_public_id,
                'id_document_url' => $upload['url'] ?? $booking->id_document_url,
                'identity_verified_at' => ($data['document_number'] ?? null) || $upload ? now() : $booking->identity_verified_at,
                'status' => 'checked_in',
                'checked_in_at' => now(),
                'checked_out_at' => null,
            ]);
            $booking->ensureCheckinConfirmation();
            $booking->save();
        });

        return response()->json(['data' => $booking->fresh()->toCheckinConfirmationArray()]);
    }

    /**
     * POST /reception/bookings/{booking}/check-out — close out a departing guest.
     *
     * Freeing the room and closing the booking stand or fall together. Idempotent
     * in the same way as check-in.
     */
    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);

        if ($booking->status === 'checked_out') {
            return response()->json(['data' => $booking->fresh()->toReceptionDepartureArray()]);
        }

        abort_unless($booking->status === 'checked_in', 409, 'This booking is not checked in.');

        DB::transaction(function () use ($booking) {
            RoomUnit::release($booking->room_unit_id);
            $booking->forceFill([
                'status' => 'checked_out',
                'checked_out_at' => now(),
            ])->save();
        });

        // The host has gone — their visitors' gate codes go with them.
        app(VisitorPassProvisioner::class)->closeOutBooking($booking->id);

        return response()->json(['data' => $booking->fresh()->toReceptionDepartureArray()]);
    }

    /* ---------------- Vehicle pickup ---------------- */

    /**
     * GET /reception/pickups — every guest vehicle pickup, newest first, so the
     * desk can see who is arriving by car and assign a driver. Optional
     * `?status=` narrows by pickup stage (unassigned | assigned | completed).
     */
    public function pickups(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $status = $request->query('status');

        $pickups = Booking::with('pickupDriver')
            ->whereNotNull('pickup_vehicle')->where('pickup_vehicle', '!=', '')
            ->whereIn('status', ['paid', 'checked_in', 'checked_out'])
            ->when(
                in_array($status, ['unassigned', 'assigned', 'completed'], true),
                fn ($q) => $q->where('pickup_status', $status),
            )
            ->orderByRaw("CASE pickup_status WHEN 'unassigned' THEN 0 WHEN 'assigned' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $pickups->map->toReceptionPickupArray()->values()]);
    }

    /**
     * GET /reception/drivers — the assignable driver roster (available only) for
     * the pickup assignment dropdown.
     */
    public function drivers(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $drivers = Driver::available()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $drivers->map->toRosterArray()->values()]);
    }

    /**
     * POST /reception/bookings/{booking}/assign-driver — assign or reassign the
     * driver for a guest's vehicle pickup. A null `driver_id` clears it.
     */
    public function assignDriver(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);

        abort_unless($booking->isPickup(), 422, 'This booking has no vehicle pickup.');

        $data = $request->validate([
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
        ]);

        $driver = ! empty($data['driver_id'])
            ? Driver::available()->find($data['driver_id'])
            : null;

        // A driver_id was given but the driver is off-duty or gone.
        abort_if(! empty($data['driver_id']) && ! $driver, 422, 'That driver is not available.');

        $booking->assignPickupDriver($driver);

        return response()->json(['data' => $booking->fresh()->load('pickupDriver')->toReceptionPickupArray()]);
    }

    /**
     * POST /reception/bookings/{booking}/pickup-complete — mark the guest as
     * collected once the driver has picked them up.
     */
    public function completePickup(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);

        abort_unless($booking->isPickup(), 422, 'This booking has no vehicle pickup.');
        abort_unless($booking->pickup_status === 'assigned', 409, 'Assign a driver before completing the pickup.');

        $booking->markPickupCompleted();

        return response()->json(['data' => $booking->fresh()->load('pickupDriver')->toReceptionPickupArray()]);
    }

    /* ---------------- SOS incidents ---------------- */

    /**
     * GET /reception/incidents — the SOS Alert Logs: every emergency ever raised,
     * most recent first, with its full timeline. Optionally narrowed by
     * `?status=` (active | acknowledged | resolved | cancelled | open).
     *
     * SOS alerts are hotel-wide, so reception sees the same incidents security
     * does and can respond to them — the front desk is often the closest staffed
     * station to a guest in trouble.
     */
    public function incidents(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $status = $request->query('status');

        $incidents = SosAlert::query()
            ->with(['acknowledgedBy:id,name', 'resolvedBy:id,name'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(
                in_array($status, [SosAlert::ACTIVE, SosAlert::ACKNOWLEDGED, SosAlert::RESOLVED, SosAlert::CANCELLED], true),
                fn ($q) => $q->where('status', $status),
            )
            ->latest('raised_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $incidents->map->toLogArray()->values()]);
    }

    /**
     * POST /reception/incidents/{alert}/respond — the receptionist acknowledges an
     * incoming SOS: the guest tablet flips to "help is on the way", and the alert
     * moves off the ACTIVE state. A second tap (or one from security) is a no-op.
     */
    public function respond(Request $request, SosAlert $alert): JsonResponse
    {
        $receptionist = $this->receptionist($request);

        $alert->acknowledge($receptionist);

        return response()->json(['data' => $alert->fresh()->toSecurityArray()]);
    }

    /**
     * POST /reception/incidents/{alert}/resolve — the incident is dealt with. Once
     * resolved it stays resolved; a later tap is a no-op.
     */
    public function resolve(Request $request, SosAlert $alert): JsonResponse
    {
        $receptionist = $this->receptionist($request);

        $alert->resolve($receptionist);

        return response()->json(['data' => $alert->fresh()->toSecurityArray()]);
    }

    /**
     * GET /reception/bookings — every room booking, newest first, optionally
     * narrowed by `?status=` and `?search=`. This is the front desk's read-only
     * view of the same reservations the admin dashboard manages.
     */
    public function bookings(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $bookings = Booking::with('roomUnit')
            ->when(
                in_array($status, ['pending', 'paid', 'checked_in', 'checked_out', 'cancelled'], true),
                fn ($q) => $q->where('status', $status),
            )
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('room_name', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $bookings->map->toReceptionBookingRowArray()->values()]);
    }

    /**
     * GET /reception/guests — the hotel's guests, aggregated from their bookings.
     *
     * There is no Guest table: a guest is a distinct person (by email, else
     * phone, else name) with one or more bookings. Each row rolls up how many
     * stays they have had and whether they are currently in-house.
     */
    public function guests(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $search = trim((string) $request->query('search', ''));

        $bookings = Booking::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")))
            ->orderByDesc('check_in')
            ->get();

        $guests = $bookings
            ->groupBy(fn (Booking $b) => $b->guestKey())
            ->map(function ($group) {
                $latest = $group->first(); // ordered newest-first
                $stays = $group->whereIn('status', ['paid', 'checked_in', 'checked_out']);
                $activeBooking = $group->firstWhere('status', 'checked_in');

                return [
                    'key' => $latest->guestKey(),
                    'name' => $latest->customer_name,
                    'email' => $latest->customer_email,
                    'phone' => $latest->customer_phone,
                    'stays' => $stays->count(),
                    'last_stay_label' => optional($latest->check_in)->format('M j, Y'),
                    'in_house' => $activeBooking !== null,
                    // Lets the list check the guest out in one tap, no need to
                    // open their profile first.
                    'active_booking_id' => $activeBooking?->id,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['data' => $guests]);
    }

    /**
     * GET /reception/guests/profile?key= — one guest's full record: their contact
     * details, lifetime stats, stay history and preferences derived from that
     * history (there is nothing to fabricate — a "preference" is just what the
     * guest has actually done before).
     */
    public function guestProfile(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $key = (string) $request->query('key', '');
        abort_if($key === '', 422, 'A guest key is required.');

        $bookings = Booking::with('roomUnit')
            ->orderByDesc('check_in')
            ->get()
            ->filter(fn (Booking $b) => $b->guestKey() === $key)
            ->values();

        abort_if($bookings->isEmpty(), 404, 'Guest not found.');

        $latest = $bookings->first();
        $stays = $bookings->whereIn('status', ['paid', 'checked_in', 'checked_out']);
        $activeBooking = $bookings->firstWhere('status', 'checked_in');

        // Favourite room and usual party size are simply the most frequent ones.
        $favouriteRoom = $stays->groupBy('room_name')->map->count()->sortDesc()->keys()->first();
        $usualParty = $stays->pluck('guests')->filter()
            ->countBy()->sortDesc()->keys()->first();

        return response()->json(['data' => [
            'key' => $key,
            'name' => $latest->customer_name,
            'email' => $latest->customer_email,
            'phone' => $latest->customer_phone,
            'in_house' => $activeBooking !== null,
            // The booking to check out when the guest is in-house — lets the desk
            // check them out from their profile even if their departure isn't
            // "today" (an extended stay, or one due later that's leaving early).
            'active_booking_id' => $activeBooking?->id,
            'stats' => [
                'total_stays' => $stays->count(),
                'total_nights' => (int) $stays->sum('nights'),
                'total_spend_label' => '₦'.number_format((int) $stays->sum('amount')),
                'first_seen_label' => optional($bookings->last()->check_in)->format('M Y'),
            ],
            'preferences' => [
                'favourite_room' => $favouriteRoom,
                'usual_party_size' => $usualParty !== null ? (int) $usualParty : null,
                'uses_airport_pickup' => $bookings->contains(fn (Booking $b) => ! empty($b->pickup_vehicle)),
            ],
            'history' => $bookings->map->toReceptionBookingRowArray()->values(),
        ]]);
    }

    /** The authenticated staffer, who must hold the `reception` role. */
    private function receptionist(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('reception'), 403, 'Reception access only.');

        return $user;
    }
}
