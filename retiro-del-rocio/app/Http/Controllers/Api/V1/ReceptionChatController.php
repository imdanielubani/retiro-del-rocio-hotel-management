<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\GuestNotification;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reception's Chat screen — two kinds of conversation from the one station:
 * Concierge Chat threads with every in-house guest ({@see ChatMessage}), and
 * internal channels with each staff department ({@see StaffMessage}). Both
 * share the same list → thread → composer shape, just scoped differently.
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

        $conversations = $bookings
            ->map(function (Booking $booking) use ($lastByBooking) {
                $messages = $lastByBooking->get($booking->id, collect());
                $last = $messages->first();

                return [
                    'booking_id' => $booking->id,
                    'guest_name' => $booking->customer_name,
                    'room_label' => $this->roomLabel($booking),
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
     * GET /reception/chat/staff — one row per department channel
     * (Housekeeping, Maintenance, Security), same preview/unread shape as
     * the guest list.
     */
    public function staffConversations(Request $request): JsonResponse
    {
        $this->receptionist($request);

        $lastByDepartment = StaffMessage::whereIn('department', StaffMessage::DEPARTMENTS)
            ->latest('created_at')
            ->get()
            ->groupBy('department');

        $conversations = collect(StaffMessage::DEPARTMENTS)
            ->map(function (string $department) use ($lastByDepartment) {
                $messages = $lastByDepartment->get($department, collect());
                $last = $messages->first();

                return [
                    'department' => $department,
                    'label' => ucfirst($department),
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at?->toIso8601String(),
                    'last_message_label' => optional($last?->created_at)->diffForHumans(['short' => true]),
                    'unread_count' => $messages->where('sender_role', $department)->whereNull('read_at')->count(),
                ];
            })
            ->values();

        return response()->json(['data' => $conversations]);
    }

    /** GET /reception/chat/staff/{department}/messages — one department's channel. */
    public function staffMessages(Request $request, string $department): JsonResponse
    {
        $this->receptionist($request);
        abort_unless(in_array($department, StaffMessage::DEPARTMENTS, true), 404, 'Unknown department.');

        $messages = StaffMessage::where('department', $department)->oldest()->get();

        StaffMessage::where('department', $department)
            ->where('sender_role', $department)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => $messages->map(fn (StaffMessage $m) => $m->toChatArray(StaffMessage::RECEPTION))->values()]);
    }

    /** POST /reception/chat/staff/{department}/messages — message a department. */
    public function sendStaffMessage(Request $request, string $department): JsonResponse
    {
        $receptionist = $this->receptionist($request);
        abort_unless(in_array($department, StaffMessage::DEPARTMENTS, true), 404, 'Unknown department.');

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = StaffMessage::create([
            'department' => $department,
            'sender_role' => StaffMessage::RECEPTION,
            'sender_name' => $receptionist->name,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $message->toChatArray(StaffMessage::RECEPTION)], 201);
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
