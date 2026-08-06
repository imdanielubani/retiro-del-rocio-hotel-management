<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Device;
use App\Models\ReceptionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Concierge Chat — a guest's in-room tablet messaging the front desk. One
 * thread per booking, scoped to the device's own room the same way every
 * other guest endpoint is. A message sent here also raises a front-desk
 * notification, so reception knows one is waiting without watching the
 * thread itself.
 */
class GuestChatController extends Controller
{
    /**
     * GET /chat/messages — this stay's conversation with the front desk,
     * oldest first. Opening the thread is treated as reading it: any unread
     * staff message is marked read as a side effect, and the count of what
     * was unread *before* that happens is returned so the conversation list
     * can still show the badge that was true when the guest tapped in.
     */
    public function index(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);
        $booking = $device->currentBooking();

        if (! $booking) {
            return response()->json(['data' => [], 'unread_count' => 0]);
        }

        $messages = ChatMessage::where('booking_id', $booking->id)->oldest()->get();

        $unreadCount = $messages->where('sender_type', ChatMessage::STAFF)->whereNull('read_at')->count();

        if ($unreadCount > 0) {
            ChatMessage::where('booking_id', $booking->id)
                ->where('sender_type', ChatMessage::STAFF)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'data' => $messages->map->toGuestArray()->values(),
            'unread_count' => $unreadCount,
        ]);
    }

    /** POST /chat/messages — send a message to the front desk. */
    public function store(Request $request): JsonResponse
    {
        $device = $this->guestDevice($request);
        $booking = $device->currentBooking();
        abort_unless($booking, 409, 'No guest is checked in to this room.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $message = ChatMessage::create([
            'booking_id' => $booking->id,
            'sender_type' => ChatMessage::GUEST,
            'sender_name' => $booking->customer_name,
            'body' => $data['body'],
        ]);

        // Front desk should know a message is waiting. Never let a hiccup
        // here fail a message the guest already has.
        try {
            ReceptionNotification::notify(
                'message',
                'New Message — '.($booking->customer_name ?: 'A guest'),
                Str::limit($data['body'], 120),
                $booking,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $message->toGuestArray()], 201);
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
