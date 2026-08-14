<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningOrder;
use App\Models\KitchenNotification;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kitchen Tablet (KDS). Authenticated by the chef/kitchen staffer's staff
 * JWT — the JWT middleware binds the user, and every endpoint re-checks the
 * `kitchen` role so a token from another station can't work this one.
 *
 * The ticket board and history reuse the exact same {@see DiningOrder}/
 * {@see MenuItem} models the Kitchen admin dashboard already manages (via
 * `DiningOrder::forKitchen()`) — a food order placed from a guest's room
 * tablet, or rung up mixed with drinks via the Bar Tablet's POS, is the
 * same record type and shows up everywhere consistently. Unlike the Bar
 * Tablet, Kitchen never runs its own POS/tabs and has no age-verification
 * gate — it only ever manages tickets through to Served.
 */
class KitchenController extends Controller
{
    /**
     * GET /kitchen/overview — the dashboard in one call: headline counters.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $orders = DiningOrder::forKitchen()->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'on_way'])->get();

        return response()->json(['data' => [
            'stats' => [
                'new' => $orders->filter(fn (DiningOrder $o) => $o->barBoardColumn() === 'new')->count(),
                'preparing' => $orders->filter(fn (DiningOrder $o) => $o->barBoardColumn() === 'preparing')->count(),
                'ready' => $orders->filter(fn (DiningOrder $o) => $o->barBoardColumn() === 'ready')->count(),
                'served_today' => DiningOrder::forKitchen()->where('status', 'delivered')
                    ->whereDate('updated_at', today())->count(),
            ],
        ]]);
    }

    /* ---------------- Live Board / Queue ---------------- */

    /**
     * GET /kitchen/orders — every active food ticket, from any source (guest
     * tablet room order, or a mixed order rung up via the Bar Tablet's POS),
     * newest first. Optional `?status=`.
     */
    public function liveOrders(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $status = $request->query('status');

        $orders = DiningOrder::forKitchen()
            ->with(['barTab', 'booking.roomUnit', 'assignedBartender'])
            ->when(
                in_array($status, ['pending', 'confirmed', 'preparing', 'ready', 'on_way', 'delivered', 'cancelled'], true),
                fn ($q) => $q->where('status', $status),
                fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'on_way']),
            )
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $orders->map->toKitchenOrderArray()->values()]);
    }

    /** GET /kitchen/orders/{order} */
    public function orderDetail(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);

        $order->load(['barTab', 'booking.roomUnit', 'assignedBartender']);

        return response()->json(['data' => $order->toKitchenOrderArray()]);
    }

    /** POST /kitchen/orders/{order}/prepare */
    public function markPreparing(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);

        $order->markPreparing();

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /**
     * POST /kitchen/orders/{order}/serve — the Mark Ready confirm dialog:
     * cooked and waiting at the pass, ready for pickup — not yet actually
     * served to the guest, which stays a Bar Tablet action.
     */
    public function markReady(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);

        abort_unless($order->markReadyForPickup(), 422, 'This ticket cannot be marked ready right now.');

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /**
     * POST /kitchen/orders/{order}/on-way — Kitchen dispatching a pure
     * room-service order (no waiter/bar tab) for delivery to the guest's
     * room. Rejected for a dine-in/POS order — that hand-off is the Bar
     * Tablet's {@see BarController::markServed()} instead.
     */
    public function markOnTheWay(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);
        abort_if($order->bar_tab_id, 422, 'This order is being served by the waiter — mark it served from the Bar Tablet.');

        abort_unless($order->markOnTheWay(), 422, 'This ticket cannot be marked on the way right now.');

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /**
     * POST /kitchen/orders/{order}/deliver — Kitchen confirming a pure
     * room-service order reached the guest.
     */
    public function markDelivered(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);
        abort_if($order->bar_tab_id, 422, 'This order is being served by the waiter — mark it served from the Bar Tablet.');

        abort_unless($order->markDeliveredToRoom(), 422, 'This ticket cannot be marked delivered right now.');

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /**
     * POST /kitchen/orders/{order}/eta — set or increase how long this
     * ticket still needs. Blocked once it's ready or closed — there's
     * nothing left to estimate at that point.
     */
    public function setEta(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);
        abort_if(in_array($order->status, ['ready', 'on_way', 'delivered', 'cancelled'], true), 422, 'This ticket is already ready or closed.');

        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:180'],
        ]);

        $order->setEta($data['minutes']);

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /** POST /kitchen/orders/{order}/void-item — the Void/Cancel Item dialog. */
    public function voidItem(Request $request, DiningOrder $order): JsonResponse
    {
        $staff = $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);

        $data = $request->validate([
            'item_index' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($order->voidItem($data['item_index'], $staff, $data['reason'] ?? null), 422, 'That item could not be voided.');

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /** POST /kitchen/orders/{order}/assign — the Assign to Station bottom sheet. */
    public function assignOrder(Request $request, DiningOrder $order): JsonResponse
    {
        $this->kitchenStaff($request);
        abort_unless($order->has_food, 404);

        $data = $request->validate(['chef_id' => ['required', 'integer', 'exists:users,id']]);

        $chef = User::find($data['chef_id']);
        abort_unless($chef?->hasRole('kitchen'), 422, 'That user is not kitchen staff.');

        $order->assignTo($chef);

        return response()->json(['data' => $order->fresh(['barTab', 'booking.roomUnit', 'assignedBartender'])->toKitchenOrderArray()]);
    }

    /** GET /kitchen/chefs — the Assign to Station bottom sheet's picker. */
    public function chefs(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $chefs = User::role('kitchen')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $chefs->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()]);
    }

    /* ---------------- Menu Availability ---------------- */

    /**
     * GET /kitchen/menu — every dish, active or not (unlike the guest
     * endpoint, staff need to see and toggle hidden items too).
     */
    public function menu(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $items = MenuItem::food()->ordered()->get();

        return response()->json(['data' => $items->map->toGuestArray()->values()]);
    }

    /** POST /kitchen/menu/{id}/toggle — the 86-item confirm's availability toggle. */
    public function toggleMenuItemAvailability(Request $request, int $id): JsonResponse
    {
        $this->kitchenStaff($request);

        $item = MenuItem::food()->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        return response()->json(['data' => $item->toGuestArray()]);
    }

    /* ---------------- History ---------------- */

    /**
     * GET /kitchen/history — served/cancelled food tickets. Optional
     * `?search=` (guest name, order reference).
     */
    public function history(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $search = trim((string) $request->query('search', ''));

        $orders = DiningOrder::forKitchen()
            ->with(['barTab', 'booking.roomUnit', 'assignedBartender'])
            ->whereIn('status', ['delivered', 'cancelled'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")))
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $orders->map->toKitchenOrderArray()->values()]);
    }

    /* ---------------- Notifications ---------------- */

    /** GET /kitchen/notifications — the new-order alert feed, newest first. */
    public function notifications(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        $notifications = KitchenNotification::with('diningOrder')->latest()->limit(100)->get();

        return response()->json(['data' => $notifications->map->toKitchenArray()->values()]);
    }

    /** POST /kitchen/notifications/{notification}/read */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $this->kitchenStaff($request);

        $record = KitchenNotification::find($notification);
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toKitchenArray()]);
    }

    /** POST /kitchen/notifications/read-all */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->kitchenStaff($request);

        KitchenNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** The authenticated staffer, who must hold the `kitchen` role. */
    private function kitchenStaff(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('kitchen'), 403, 'Kitchen access only.');

        return $user;
    }
}
