<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuestNotification;
use App\Models\HousekeepingNotification;
use App\Models\HousekeepingRequest;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Housekeeping tablet. Authenticated by the housekeeper's staff JWT — the JWT
 * middleware binds the user, and every endpoint re-checks the `housekeeping`
 * role so a token from another station can't work this one.
 *
 * Hotel-wide, like reception and security: housekeeping watches every room,
 * not one.
 */
class HousekeepingController extends Controller
{
    /**
     * GET /housekeeping/overview — the dashboard in one call: headline
     * counters, the rooms most needing attention (dirty or out of order,
     * checkout-today rooms first — a room about to turn over is more urgent
     * than a mid-stay tidy), and the newest pending guest requests.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        $rooms = RoomUnit::with(['room', 'booking'])->get();
        $inspectionBookingIds = $this->pendingInspectionBookingIds();

        $needsInspection = fn (RoomUnit $u) => $u->booking_id && $inspectionBookingIds->contains($u->booking_id);

        $needsAttention = $rooms
            ->filter(fn (RoomUnit $u) => in_array($u->housekeeping_status, ['dirty', 'out_of_order'], true)
                || $needsInspection($u))
            ->sortBy(fn (RoomUnit $u) => match (true) {
                $needsInspection($u) => 0,
                $u->booking?->status === 'checked_in' && optional($u->booking->check_out)->isToday() => 1,
                $u->housekeeping_status === 'out_of_order' => 2,
                default => 3,
            })
            ->values();

        $requests = HousekeepingRequest::with('roomUnit.room')
            ->where('status', HousekeepingRequest::PENDING)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['data' => [
            'stats' => [
                'needs_attention' => $needsAttention->count(),
                'dirty' => $rooms->where('housekeeping_status', 'dirty')->count(),
                'out_of_order' => $rooms->where('housekeeping_status', 'out_of_order')->count(),
                'pending_requests' => HousekeepingRequest::where('status', HousekeepingRequest::PENDING)->count(),
            ],
            'rooms' => $needsAttention->take(20)
                ->map(fn (RoomUnit $u) => $u->toHousekeepingRoomArray($needsInspection($u)))
                ->values(),
            'requests' => $requests->map->toHousekeepingArray()->values(),
        ]]);
    }

    /** Booking ids with an open (pending) pre-checkout inspection request. */
    private function pendingInspectionBookingIds(): Collection
    {
        return HousekeepingRequest::where('type', HousekeepingRequest::CHECKOUT_INSPECTION)
            ->where('status', HousekeepingRequest::PENDING)
            ->whereNotNull('booking_id')
            ->pluck('booking_id')
            ->unique();
    }

    /**
     * GET /housekeeping/rooms — every room, newest-attention-needed first.
     * Optional `?status=` narrows by housekeeping status; `?search=` by room
     * number.
     */
    public function rooms(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $rooms = RoomUnit::with(['room', 'booking'])
            ->when(
                in_array($status, RoomUnit::HOUSEKEEPING_STATUSES, true),
                fn ($q) => $q->where('housekeeping_status', $status),
            )
            ->when($search !== '', fn ($q) => $q->where('number', 'like', "%{$search}%"))
            ->orderByRaw("CASE housekeeping_status WHEN 'out_of_order' THEN 0 WHEN 'dirty' THEN 1 WHEN 'inspected' THEN 2 ELSE 3 END")
            ->orderBy('number')
            ->get();

        $inspectionBookingIds = $this->pendingInspectionBookingIds();

        return response()->json(['data' => $rooms
            ->map(fn (RoomUnit $u) => $u->toHousekeepingRoomArray(
                $u->booking_id && $inspectionBookingIds->contains($u->booking_id),
            ))
            ->values(),
        ]);
    }

    /**
     * POST /housekeeping/rooms/{unit}/status — mark a room clean, inspected,
     * dirty again, or out of order.
     */
    public function updateRoomStatus(Request $request, RoomUnit $unit): JsonResponse
    {
        $this->housekeeper($request);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', RoomUnit::HOUSEKEEPING_STATUSES)],
        ]);

        $unit->update([
            'housekeeping_status' => $data['status'],
            'housekeeping_status_at' => now(),
        ]);

        return response()->json(['data' => $unit->fresh(['room', 'booking'])->toHousekeepingRoomArray()]);
    }

    /**
     * GET /housekeeping/requests — guest requests, newest first so the desk
     * sees a freshly submitted ask right away. Optional `?status=` narrows
     * to pending or completed.
     */
    public function requests(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        $status = $request->query('status');

        $requests = HousekeepingRequest::with('roomUnit.room')
            ->when(
                in_array($status, [HousekeepingRequest::PENDING, HousekeepingRequest::COMPLETED], true),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $requests->map->toHousekeepingArray()->values()]);
    }

    /**
     * POST /housekeeping/requests — log a guest ask (towels, amenities, DND,
     * make-up room) against a room.
     */
    public function createRequest(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        $data = $request->validate([
            'room_unit_id' => ['required', 'integer', 'exists:room_units,id'],
            'type' => ['required', 'in:'.implode(',', HousekeepingRequest::TYPES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $unit = RoomUnit::find($data['room_unit_id']);

        $entry = HousekeepingRequest::create([
            'room_unit_id' => $unit->id,
            'booking_id' => $unit->booking_id,
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $entry->fresh('roomUnit')->toHousekeepingArray()], 201);
    }

    /** POST /housekeeping/requests/{request}/complete — clear a guest ask. */
    public function completeRequest(Request $request, HousekeepingRequest $housekeepingRequest): JsonResponse
    {
        $officer = $this->housekeeper($request);

        if ($housekeepingRequest->complete($officer)) {
            $this->notifyGuestRequestCompleted($housekeepingRequest);
        }

        return response()->json(['data' => $housekeepingRequest->fresh('roomUnit')->toHousekeepingArray()]);
    }

    /**
     * Push a live signal to the guest's own tablet the moment their request is
     * cleared, over the room's existing realtime channel, so their Service
     * Request history updates in a couple of seconds rather than waiting on
     * its own poll. The reception-initiated checkout inspection is skipped —
     * it never appears in the guest's history in the first place (see
     * {@see GuestServiceRequestController::index()}).
     */
    private function notifyGuestRequestCompleted(HousekeepingRequest $housekeepingRequest): void
    {
        if ($housekeepingRequest->type === HousekeepingRequest::CHECKOUT_INSPECTION) {
            return;
        }

        $booking = $housekeepingRequest->booking()->first();
        $unit = $housekeepingRequest->roomUnit()->first();

        if (! $booking || ! $unit) {
            return;
        }

        GuestNotification::notify(
            $booking,
            $unit,
            'housekeeping',
            'Housekeeping Request Completed',
            'Your '.$housekeepingRequest->typeLabel().' request has been completed.',
        );
    }

    /* ---------------- Notifications ---------------- */

    /**
     * GET /housekeeping/notifications — housekeeping's notification feed,
     * newest first. Today the only trigger is a guest raising a housekeeping
     * request; housekeeping is one shared station, so the feed (and its read
     * state) is shared across whoever is signed in, not scoped to a single
     * housekeeper.
     */
    public function notifications(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        $notifications = HousekeepingNotification::with('booking.roomUnit')->latest()->limit(100)->get();

        return response()->json(['data' => $notifications->map->toHousekeepingArray()->values()]);
    }

    /** POST /housekeeping/notifications/{notification}/read — mark one as read. */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $this->housekeeper($request);

        $record = HousekeepingNotification::find($notification);
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toHousekeepingArray()]);
    }

    /** POST /housekeeping/notifications/read-all — "Mark all read". */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->housekeeper($request);

        HousekeepingNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** The authenticated staffer, who must hold the `housekeeping` role. */
    private function housekeeper(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('housekeeping'), 403, 'Housekeeping access only.');

        return $user;
    }
}
