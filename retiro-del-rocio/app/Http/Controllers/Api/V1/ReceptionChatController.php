<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatTypingSent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\Device;
use App\Models\GuestNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reception's Chat screen — Concierge Chat threads with every in-house guest
 * ({@see ChatMessage}). Reception's own internal staff-to-staff channels
 * (Housekeeping, Maintenance, Security, Admin) are served by the shared
 * {@see StaffChatController}, the same one every other station's tablet uses.
 */
class ReceptionChatController extends Controller
{
    /**
     * GET /reception/chat/guests — one row per checked-in guest, newest
     * conversation first, with a preview and how many of their messages are
     * still unread by the front desk.
     */
    public function guestConversations(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $bookings = Booking::where('status', 'checked_in')->with('roomUnit')->get();

        $lastByBooking = ChatMessage::whereIn('booking_id', $bookings->pluck('id'))
            ->latest('created_at')
            ->get()
            ->groupBy('booking_id');

        $devicesByRoomUnit = Device::mode('guest')
            ->whereIn('room_unit_id', $bookings->pluck('room_unit_id')->filter())
            ->get()
            ->keyBy('room_unit_id');

        $conversations = $bookings
            ->map(function (Booking $booking) use ($lastByBooking, $devicesByRoomUnit) {
                $messages = $lastByBooking->get($booking->id, collect());
                $last = $messages->first();
                $device = $devicesByRoomUnit->get($booking->room_unit_id);

                return [
                    'booking_id' => $booking->id,
                    'room_unit_id' => $booking->room_unit_id,
                    'guest_name' => $booking->customer_name,
                    'room_label' => $this->roomLabel($booking),
                    'guest_online' => $device?->isRecentlyActive() ?? false,
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at?->toIso8601String(),
                    'last_message_label' => optional($last?->created_at)->diffForHumans(['short' => true]),
                    'unread_count' => $messages->where('sender_type', ChatMessage::GUEST)->whereNull('read_at')->count(),
                ];
            })
            ->sortByDesc(fn (array $c) => $c['last_message_at'] ?? '')
            ->values();

        return response()->json(['data' => $conversations]);
    }

    /**
     * GET /reception/chat/guests/{booking}/messages — one guest's thread,
     * oldest first. Opening it marks their unread messages read, the same
     * "opening is reading" rule the guest side already follows.
     */
    public function guestMessages(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);
        abort_unless($booking->status === 'checked_in', 404, 'This guest is not checked in.');

        $messages = ChatMessage::where('booking_id', $booking->id)->oldest()->get();

        ChatMessage::where('booking_id', $booking->id)
            ->where('sender_type', ChatMessage::GUEST)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages->map->toStaffArray()->values()]);
    }

    /** POST /reception/chat/guests/{booking}/messages — reply to a guest. */
    public function sendGuestMessage(Request $request, Booking $booking): JsonResponse
    {
        $receptionist = $this->receptionist($request);
        abort_unless($booking->status === 'checked_in', 409, 'This guest is not checked in.');

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = ChatMessage::create([
            'booking_id' => $booking->id,
            'sender_type' => ChatMessage::STAFF,
            'sender_name' => $receptionist->name,
            'body' => $data['body'],
        ]);

        // Push straight to the guest's own tablet over its existing realtime
        // channel; the periodic poll is the fallback if the socket is down.
        try {
            $unit = $booking->roomUnit()->first();
            if ($unit) {
                GuestNotification::notify($booking, $unit, 'message', 'New Message — Reception', Str::limit($data['body'], 120));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $message->toStaffArray()], 201);
    }

    /**
     * POST /reception/chat/guests/{booking}/typing — a fire-and-forget "I'm
     * typing" signal, never persisted. Picked up by the guest tablet's
     * already-open realtime connection to its own room.
     */
    public function typingToGuest(Request $request, Booking $booking): JsonResponse
    {
        $this->receptionist($request);

        try {
            $unit = $booking->roomUnit()->first();
            if ($unit) {
                broadcast(new ChatTypingSent($unit->id, ChatMessage::STAFF));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => true]);
    }

    private function roomLabel(Booking $booking): string
    {
        $unit = $booking->relationLoaded('roomUnit') ? $booking->roomUnit : $booking->roomUnit()->first();

        return implode(' · ', array_filter([$booking->room_name, $unit?->number ? 'Room '.$unit->number : null]));
    }

    /** The calling staff member, who must hold the reception role. */
    private function receptionist(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('reception'), 403, 'Reception access only.');

        return $user;
    }
}
