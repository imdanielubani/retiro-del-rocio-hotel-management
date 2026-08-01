<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Device;
use App\Models\HousekeepingNotification;
use App\Models\HousekeepingRequest;
use App\Models\ReceptionNotification;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Housekeeping / maintenance requests raised from a guest's in-room tablet's
 * "Service Request" quick-service tile.
 *
 * Both categories already have their own staff-facing lifecycle —
 * housekeeping's Requests screen, maintenance's Work Orders board — this
 * controller is just the guest-facing front door onto the same two tables,
 * scoped to the device's own room the same way every other guest endpoint
 * is. A row created here shows up on the relevant staff tablet on its next
 * poll; no separate hand-off is needed.
 */
class GuestServiceRequestController extends Controller
{
    /**
     * GET /service-requests — this stay's history, both categories combined,
     * newest first, so the guest can see what happened to what they asked
     * for. Scoped to the current booking — the same "nothing to show between
     * guests" rule visitor passes and every other guest history follows.
     */
    public function index(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);
        $booking = $device->currentBooking();

        if (! $booking) {
            return response()->json(['data' => []]);
        }

        $housekeeping = HousekeepingRequest::where('booking_id', $booking->id)->get();
        $maintenance = WorkOrder::where('booking_id', $booking->id)->get();

        $all = $housekeeping->map->toGuestArray()
            ->concat($maintenance->map->toGuestArray())
            ->sortByDesc('created_at')
            ->values();

        return response()->json(['data' => $all]);
    }

    /**
     * POST /service-requests — raise a housekeeping ask or a maintenance
     * fault against this room.
     */
    public function store(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);
        $booking = $device->currentBooking();
        abort_unless($booking, 409, 'No guest is checked in to this room.');

        $category = $request->validate([
            'category' => ['required', 'in:housekeeping,maintenance'],
        ])['category'];

        $entry = $category === 'housekeeping'
            ? $this->createHousekeepingRequest($request, $device, $booking)
            : $this->createWorkOrder($request, $device, $booking);

        // Front desk should know a guest just raised a service request, so
        // reception isn't the only station unaware one is coming. Never let
        // a hiccup here fail a request the guest already has.
        try {
            ReceptionNotification::notify(
                'guest',
                $category === 'housekeeping' ? 'Housekeeping Request' : 'Maintenance Request',
                ($booking->customer_name ?: 'A guest').' requested '.
                    ($category === 'housekeeping' ? $entry->typeLabel() : $entry->title).'.',
                $booking,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        // Housekeeping needs to know an ask is waiting before it turns up on
        // the next poll of its own Requests screen. A maintenance fault is
        // not housekeeping's concern, so this only fires for that category —
        // independent of the reception notification above, so a hiccup in
        // one never silences the other.
        if ($category === 'housekeeping') {
            try {
                HousekeepingNotification::notify(
                    'guest',
                    'New Housekeeping Request',
                    ($booking->customer_name ?: 'A guest').' in Room '.($device->roomUnit?->number ?? '—').
                        ' requested '.$entry->typeLabel().'.',
                    $booking,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $entry->toGuestArray()], 201);
    }

    private function createHousekeepingRequest(Request $request, Device $device, Booking $booking): HousekeepingRequest
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', HousekeepingRequest::TYPES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return HousekeepingRequest::create([
            'room_unit_id' => $device->room_unit_id,
            'booking_id' => $booking->id,
            'type' => $data['type'],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function createWorkOrder(Request $request, Device $device, Booking $booking): WorkOrder
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'in:'.implode(',', WorkOrder::PRIORITIES)],
        ]);

        return WorkOrder::create([
            'room_unit_id' => $device->room_unit_id,
            'booking_id' => $booking->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'reported_by' => $booking->customer_name,
        ]);
    }

    /** The calling device, which must be a guest tablet bound to a room. */
    private function guestDevice(Request $request): Device
    {
        $device = $request->user();

        abort_unless($device instanceof Device, 403, 'Device token required.');
        abort_unless($device->room_unit_id, 409, 'This tablet is not assigned to a room.');

        return $device;
    }
}
