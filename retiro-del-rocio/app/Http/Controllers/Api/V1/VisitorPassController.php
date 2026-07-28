<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionVisitorPass;
use App\Models\Device;
use App\Models\ReceptionNotification;
use App\Models\VisitorPass;
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
     * GET /visitor-passes — the passes issued during the current stay, newest first.
     *
     * Drives the guest tablet's "Visitor History" list. Scoped to the booking, not
     * the room: the tablet stays behind when a guest checks out, and the previous
     * guest's visitor list is their business — names, emails and phone numbers of
     * people who came to see them. An empty room simply has no history to show.
     */
    public function index(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);

        $booking = $device->currentBooking();
        if (! $booking) {
            return response()->json(['data' => []]);
        }

        $passes = VisitorPass::where('booking_id', $booking->id)
            ->where('room_unit_id', $device->room_unit_id) // belt and braces
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

        $unit = $device->roomUnit()->with('room')->first();
        $booking = $device->currentBooking();

        // Only a guest actually staying here can invite anyone. Without a booking
        // to own it a pass would belong to nobody: no host to name at the gate,
        // and no stay to scope it to afterwards.
        abort_unless($booking, 409, 'No guest is checked in to this room.');

        $pass = VisitorPass::create([
            'device_id' => $device->id,
            'room_unit_id' => $device->room_unit_id,
            'booking_id' => $booking->id,
            'host_name' => $booking->customer_name,
            'room_number' => $unit?->number,
            'suite_name' => optional($unit?->room)->name,
            'visitor_name' => $data['visitor_name'],
            'visitor_email' => $data['visitor_email'] ?? null,
            'visitor_phone' => $data['visitor_phone'] ?? null,
            'code' => VisitorPass::generateUniqueCode(),
            'status' => VisitorPass::PENDING,
            // The offline code is live the moment the row commits; the online
            // (TTLock) code is still being pushed to the gates.
            'ttlock_status' => 'pending',
        ]);

        // Minting the online code means an HTTP round trip per gate plus an SMTP
        // hand-off — far longer than the tablet will wait, and a timeout there
        // read to the guest as a failure even though the pass existed. So hand
        // that work off to run once this response has been sent; the pass is
        // already usable on its offline code either way.
        ProvisionVisitorPass::dispatch($pass->id)->afterResponse();

        $device->log('visitor_pass', 'Visitor pass issued from the room tablet.', [
            'pass_id' => $pass->id,
            'visitor' => $pass->visitor_name,
        ]);

        // Front desk should know a guest just invited a visitor, so security
        // isn't the only station aware a pass is coming. Never let a hiccup here
        // fail a pass the guest already has.
        try {
            ReceptionNotification::notify(
                'guest',
                'Visitor Pass Issued',
                ($booking->customer_name ?: 'A guest').' in Room '.($unit?->number ?? '—').
                    ' invited '.$pass->visitor_name.'.',
                $booking,
            );
        } catch (\Throwable $e) {
            report($e);
        }

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
