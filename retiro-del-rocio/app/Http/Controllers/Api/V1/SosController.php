<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SosAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Emergency SOS, raised from a guest's in-room tablet.
 *
 * All three endpoints are scoped to the device's own room: a tablet can only
 * raise, read or stand down an alert for the room it is physically bound to.
 */
class SosController extends Controller
{
    /**
     * GET /sos/active — the open alert for this tablet's room, if any.
     *
     * The tablet calls this on launch so a relaunch (or a crash) mid-emergency
     * still shows "Help is on the way" rather than a fresh SOS button.
     */
    public function active(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $alert = SosAlert::open()
            ->where('room_unit_id', $device->room_unit_id)
            ->latest('raised_at')
            ->first();

        return response()->json(['data' => $alert?->toTabletArray()]);
    }

    /**
     * POST /sos — raise an emergency for this tablet's room.
     *
     * Idempotent: a frightened guest will press the button repeatedly, and a
     * dozen duplicate alerts would bury the one that matters. If the room already
     * has an open alert we return that one instead of creating another.
     */
    public function store(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $existing = SosAlert::open()
            ->where('room_unit_id', $device->room_unit_id)
            ->latest('raised_at')
            ->first();

        if ($existing) {
            return response()->json(['data' => $existing->toTabletArray()], 200);
        }

        $unit = $device->roomUnit()->with(['room', 'booking'])->first();
        $booking = $unit?->booking?->status === 'checked_in' ? $unit->booking : null;

        $alert = SosAlert::create([
            'device_id' => $device->id,
            'room_unit_id' => $device->room_unit_id,
            'booking_id' => $booking?->id,
            'room_number' => $unit?->number,
            'suite_name' => optional($unit?->room)->name,
            'guest_name' => $booking?->customer_name,
            'status' => SosAlert::ACTIVE,
            'raised_at' => now(),
        ]);

        $device->log('sos', 'Emergency SOS raised from the room tablet.', [
            'alert_id' => $alert->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['data' => $alert->toTabletArray()], 201);
    }

    /**
     * POST /sos/{alert}/cancel — the guest stands the alert down.
     *
     * Only their own room's alert, and only while it is still open. Once security
     * has resolved it, it stays resolved: a guest cannot rewrite the record.
     */
    public function cancel(Request $request, SosAlert $alert): JsonResponse
    {
        $device = $this->guestDevice($request);

        abort_unless($alert->room_unit_id === $device->room_unit_id, 403, 'This alert belongs to another room.');

        if (! $alert->isOpen()) {
            return response()->json(['data' => $alert->toTabletArray()]);
        }

        $alert->update([
            'status' => SosAlert::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => 'guest',
        ]);

        $device->log('sos', 'Emergency SOS cancelled by the guest.', ['alert_id' => $alert->id]);

        return response()->json(['data' => $alert->fresh()->toTabletArray()]);
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
