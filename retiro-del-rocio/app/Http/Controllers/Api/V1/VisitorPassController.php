<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\VisitorPass;
use App\Services\VisitorPassProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visitor passes issued from a guest's in-room tablet.
 *
 * Both endpoints are scoped to the device's own room: a tablet can only issue or
 * read passes for the room it is physically bound to. Security verifies the
 * visitor at the gate through its own (JWT-authorised) endpoints.
 */
class VisitorPassController extends Controller
{
    /**
     * GET /visitor-passes — this room's passes, newest first.
     *
     * Drives the guest tablet's "Visitor History" list.
     */
    public function index(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $passes = VisitorPass::where('room_unit_id', $device->room_unit_id)
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $passes->map->toTabletArray()->all(),
        ]);
    }

    /**
     * POST /visitor-passes — invite a visitor and mint their entry code.
     *
     * The host (guest) and room are snapshotted onto the pass so the record still
     * reads correctly after check-out. A fresh 6-digit code, unique among live
     * passes, is generated server-side — the tablet never picks the code.
     */
    public function store(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $data = $request->validate([
            'visitor_name' => ['required', 'string', 'max:80'],
            'visitor_email' => ['nullable', 'email', 'max:120'],
            'visitor_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $unit = $device->roomUnit()->with(['room', 'booking'])->first();
        $booking = $unit?->booking?->status === 'checked_in' ? $unit->booking : null;

        $pass = VisitorPass::create([
            'device_id' => $device->id,
            'room_unit_id' => $device->room_unit_id,
            'booking_id' => $booking?->id,
            'host_name' => $booking?->customer_name,
            'room_number' => $unit?->number,
            'suite_name' => optional($unit?->room)->name,
            'visitor_name' => $data['visitor_name'],
            'visitor_email' => $data['visitor_email'] ?? null,
            'visitor_phone' => $data['visitor_phone'] ?? null,
            'code' => VisitorPass::generateUniqueCode(),
            'status' => VisitorPass::PENDING,
        ]);

        // Mint the one-time online (TTLock) code now so the guest sees it on the
        // success screen; provisioning never throws — a lock that is offline just
        // leaves the pass on its manual offline code. Then email the visitor.
        $provisioner = app(VisitorPassProvisioner::class);
        $provisioner->provision($pass);
        $provisioner->email($pass);

        $device->log('visitor_pass', 'Visitor pass issued from the room tablet.', [
            'pass_id' => $pass->id,
            'visitor' => $pass->visitor_name,
        ]);

        return response()->json(['data' => $pass->fresh()->toTabletArray()], 201);
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
