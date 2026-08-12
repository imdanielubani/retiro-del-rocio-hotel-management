<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BarNotification;
use App\Models\BarTab;
use App\Models\Booking;
use App\Models\DiningOrder;
use App\Models\MenuItem;
use App\Models\RestaurantReservation;
use App\Models\User;
use App\Support\DiningOrderPricer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Bar Tablet. Authenticated by the waiter/bartender's staff JWT — the JWT
 * middleware binds the user, and every endpoint re-checks the `bar` role so
 * a token from another station can't work this one.
 *
 * The board and history reuse the exact same {@see DiningOrder}/{@see MenuItem}
 * models the Bar & Lounge admin dashboard already manages (via
 * `DiningOrder::forBarLounge()`) — a POS order rung up here, and a drink
 * order a guest placed from their room tablet, are the same record type and
 * both show up everywhere consistently.
 */
class BarController extends Controller
{
    /**
     * GET /bar/overview — the dashboard in one call: headline counters.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->bartender($request);

        $orders = DiningOrder::forBarLounge()->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'on_way'])->get();

        return response()->json(['data' => [
            'stats' => [
                'new' => $orders->filter(fn (DiningOrder $o) => $o->barBoardColumn() === 'new')->count(),
                'preparing' => $orders->filter(fn (DiningOrder $o) => $o->barBoardColumn() === 'preparing')->count(),
                'served_today' => DiningOrder::forBarLounge()->where('status', 'delivered')
                    ->whereDate('updated_at', today())->count(),
                'open_tabs' => BarTab::open()->count(),
                'vip_tabs' => BarTab::open()->where('is_vip', true)->count(),
            ],
        ]]);
    }

    /* ---------------- Live Orders ---------------- */

    /**
     * GET /bar/orders — every active drink order, from any source (guest
     * tablet or this tablet's own POS), newest first. Optional `?status=`.
     */
    public function liveOrders(Request $request): JsonResponse
    {
        $this->bartender($request);

        $status = $request->query('status');

        $orders = DiningOrder::forBarLounge()
            ->with(['barTab', 'assignedBartender'])
            ->when(
                in_array($status, ['pending', 'confirmed', 'preparing', 'ready', 'on_way', 'delivered', 'cancelled'], true),
                fn ($q) => $q->where('status', $status),
                fn ($q) => $q->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'on_way']),
            )
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $orders->map->toBarOrderArray()->values()]);
    }

    /** GET /bar/orders/{order} */
    public function orderDetail(Request $request, DiningOrder $order): JsonResponse
    {
        $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);

        $order->load(['barTab', 'assignedBartender']);

        return response()->json(['data' => $order->toBarOrderArray()]);
    }

    /** POST /bar/orders/{order}/prepare */
    public function markPreparing(Request $request, DiningOrder $order): JsonResponse
    {
        $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);
        abort_unless($order->has_food, 422, 'Drink-only orders move straight from New to Served — there is no preparing stage.');

        $order->markPreparing();

        return response()->json(['data' => $order->fresh(['barTab', 'assignedBartender'])->toBarOrderArray()]);
    }

    /** POST /bar/orders/{order}/serve — the Mark Served dialog. */
    public function markServed(Request $request, DiningOrder $order): JsonResponse
    {
        $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);
        abort_if($order->requiresAgeVerification() && ! $order->age_verified_at, 422, 'Verify the guest\'s age before serving this order.');

        $order->markServed();

        return response()->json(['data' => $order->fresh(['barTab', 'assignedBartender'])->toBarOrderArray()]);
    }

    /** POST /bar/orders/{order}/void-item — the Void Item dialog. */
    public function voidItem(Request $request, DiningOrder $order): JsonResponse
    {
        $staff = $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);

        $data = $request->validate([
            'item_index' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($order->voidItem($data['item_index'], $staff, $data['reason'] ?? null), 422, 'That item could not be voided.');

        return response()->json(['data' => $order->fresh(['barTab', 'assignedBartender'])->toBarOrderArray()]);
    }

    /** POST /bar/orders/{order}/verify-age — the Age Verification dialog. */
    public function verifyAge(Request $request, DiningOrder $order): JsonResponse
    {
        $staff = $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);

        $order->verifyAge($staff);

        return response()->json(['data' => $order->fresh(['barTab', 'assignedBartender'])->toBarOrderArray()]);
    }

    /** POST /bar/orders/{order}/assign — the Assign Bartender bottom sheet. */
    public function assignOrder(Request $request, DiningOrder $order): JsonResponse
    {
        $this->bartender($request);
        abort_unless($order->belongsToBarLounge(), 404);

        $data = $request->validate(['bartender_id' => ['required', 'integer', 'exists:users,id']]);

        $bartender = User::find($data['bartender_id']);
        abort_unless($bartender?->hasRole('bar'), 422, 'That user is not bar staff.');

        $order->assignTo($bartender);

        return response()->json(['data' => $order->fresh(['barTab', 'assignedBartender'])->toBarOrderArray()]);
    }

    /** GET /bar/bartenders — the Assign Bartender bottom sheet's picker. */
    public function bartenders(Request $request): JsonResponse
    {
        $this->bartender($request);

        $bartenders = User::role('bar')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $bartenders->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()]);
    }

    /* ---------------- Reservations ---------------- */

    /**
     * GET /bar/reservations/{code} — a waiter looks up the table/lounge
     * reservation code a walk-in guest gives them at the door, before
     * pushing it into a tab. Any status is returned (so the tablet can show
     * "already seated" etc.); {@see RestaurantReservation::toBarLookupArray()}'s
     * `can_open_tab` tells the tablet whether it's still usable.
     */
    public function lookupReservation(Request $request, string $code): JsonResponse
    {
        $this->bartender($request);

        $reservation = RestaurantReservation::whereIn('area', ['dining', 'lounge'])
            ->where(fn ($q) => $q->where('code', trim($code))->orWhere('reference', trim($code)))
            ->first();

        abort_unless($reservation, 404, 'No table or lounge reservation found with that code.');

        return response()->json(['data' => $reservation->toBarLookupArray()]);
    }

    /**
     * POST /bar/reservations/{reservation}/confirm — turns a confirmed
     * reservation straight into an open tab (pre-filled with its table/
     * lounge and guest name) so the waiter never has to re-enter what the
     * reservation already captured, then flips the reservation to `seated`
     * so it can't be pushed into a second tab.
     */
    public function confirmReservationToTab(Request $request, RestaurantReservation $reservation): JsonResponse
    {
        $staff = $this->bartender($request);

        abort_unless(
            $reservation->status === 'confirmed',
            422,
            'This reservation is '.strtolower($reservation->statusLabel()).' and can no longer be opened as a tab.'
        );

        $tab = BarTab::create([
            'code' => BarTab::makeCode(),
            'restaurant_reservation_id' => $reservation->id,
            'table_label' => $reservation->table_label ?: $reservation->areaLabel(),
            'guest_name' => $reservation->customer_name,
            'is_vip' => false,
            'opened_by' => $staff->id,
            'assigned_to' => $staff->id,
            'status' => 'open',
        ]);

        $reservation->update(['status' => 'seated']);

        return response()->json(['data' => $tab->fresh(['bartender', 'openedBy', 'orders', 'reservation'])->toBarDetailArray()], 201);
    }

    /* ---------------- Tabs / POS ---------------- */

    /** GET /bar/tabs — optional `?status=open|settled`, defaults to open. */
    public function tabs(Request $request): JsonResponse
    {
        $this->bartender($request);

        $status = $request->query('status', 'open');

        $tabs = BarTab::with(['bartender', 'openedBy', 'orders'])
            ->when(in_array($status, BarTab::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(150)
            ->get();

        return response()->json(['data' => $tabs->map->toBarArray()->values()]);
    }

    /** GET /bar/tabs/{tab} — the Tab Detail screen. */
    public function tabDetail(Request $request, BarTab $tab): JsonResponse
    {
        $this->bartender($request);

        $tab->load(['bartender', 'openedBy', 'orders']);

        return response()->json(['data' => $tab->toBarDetailArray()]);
    }

    /** POST /bar/tabs — a waiter opens a new tab. */
    public function openTab(Request $request): JsonResponse
    {
        $staff = $this->bartender($request);

        $data = $request->validate([
            'table_label' => ['nullable', 'string', 'max:120'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'is_vip' => ['nullable', 'boolean'],
        ]);

        $tab = BarTab::create([
            'code' => BarTab::makeCode(),
            'table_label' => $data['table_label'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'is_vip' => $data['is_vip'] ?? false,
            'opened_by' => $staff->id,
            'assigned_to' => $staff->id,
            'status' => 'open',
        ]);

        return response()->json(['data' => $tab->fresh(['bartender', 'openedBy', 'orders'])->toBarDetailArray()], 201);
    }

    /**
     * POST /bar/tabs/{tab}/orders — ring up drinks against an open tab. The
     * same server-side pricing discipline as the guest tablet: prices are
     * read fresh from the database, never trusted from the client.
     */
    public function addOrderToTab(Request $request, BarTab $tab): JsonResponse
    {
        $staff = $this->bartender($request);
        abort_unless($tab->status === 'open', 422, 'This tab is no longer open.');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $quote = DiningOrderPricer::quote($data['items']);

        $order = DiningOrder::create([
            'booking_id' => null,
            'bar_tab_id' => $tab->id,
            'reference' => 'BAR-'.$tab->id.'-'.strtoupper(Str::random(10)),
            'items' => $quote['lineItems'],
            'has_food' => $quote['hasFood'],
            'has_drinks' => $quote['hasDrinks'],
            'item_count' => $quote['itemCount'],
            'subtotal' => $quote['subtotal'],
            'vat' => $quote['vat'],
            'service_fee' => $quote['serviceFee'],
            'total' => $quote['total'],
            'customer_name' => $tab->guest_name,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'assigned_to' => $staff->id,
        ]);

        $tab->recalculateTotals();

        return response()->json(['data' => $tab->fresh(['bartender', 'openedBy', 'orders'])->toBarDetailArray()], 201);
    }

    /** POST /bar/tabs/{tab}/close — the Close Tab confirm → Settle Payment flow. */
    public function closeTab(Request $request, BarTab $tab): JsonResponse
    {
        $this->bartender($request);
        abort_unless($tab->status === 'open', 422, 'This tab is already closed.');

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,card,bank_transfer,room_charge'],
            'booking_id' => ['required_if:payment_method,room_charge', 'nullable', 'integer', 'exists:bookings,id'],
        ]);

        if ($data['payment_method'] === 'room_charge') {
            $booking = Booking::find($data['booking_id']);
            abort_unless($booking?->status === 'checked_in', 422, 'That room is not currently checked in.');
        }

        $tab->settle($data['payment_method'], $data['booking_id'] ?? null);

        return response()->json(['data' => $tab->fresh(['bartender', 'openedBy', 'orders', 'booking'])->toBarDetailArray()]);
    }

    /** POST /bar/tabs/{tab}/vip — the VIP flag toggle. */
    public function toggleVip(Request $request, BarTab $tab): JsonResponse
    {
        $this->bartender($request);

        $tab->toggleVip();

        return response()->json(['data' => $tab->fresh(['bartender', 'openedBy', 'orders'])->toBarArray()]);
    }

    /**
     * GET /bar/bookings/in-house — the "Charge to Room" room picker: every
     * currently checked-in guest, optionally narrowed by `?search=` (guest
     * name or room number), the same "in-house" test Reception's own Bills
     * screen uses.
     */
    public function inHouseBookings(Request $request): JsonResponse
    {
        $this->bartender($request);

        $search = trim((string) $request->query('search', ''));

        $bookings = Booking::with('roomUnit')
            ->where('status', 'checked_in')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('room_name', 'like', "%{$search}%")))
            ->orderBy('customer_name')
            ->limit(50)
            ->get();

        return response()->json(['data' => $bookings->map(fn (Booking $b) => [
            'booking_id' => $b->id,
            'guest_name' => $b->customer_name,
            'room_label' => $b->roomUnit?->number ? 'Room '.$b->roomUnit->number : ($b->room_name ?: '—'),
        ])->values()]);
    }

    /* ---------------- Menu ---------------- */

    /**
     * GET /bar/menu — every drink AND kitchen food item, active or not
     * (unlike the guest endpoint, staff need to see and toggle hidden items
     * too). The waiter's POS rings up both — food items still flow to the
     * Kitchen via the same `has_food` flag `DiningOrderPricer` already sets,
     * ready for the Kitchen Tablet once that's built.
     */
    public function menu(Request $request): JsonResponse
    {
        $this->bartender($request);

        $items = MenuItem::ordered()->get();

        return response()->json(['data' => $items->map->toGuestArray()->values()]);
    }

    /** POST /bar/menu/{id}/toggle — availability toggle. */
    public function toggleMenuItemAvailability(Request $request, int $id): JsonResponse
    {
        $this->bartender($request);

        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        return response()->json(['data' => $item->toGuestArray()]);
    }

    /* ---------------- History ---------------- */

    /**
     * GET /bar/history — settled tabs and served/cancelled drink orders not
     * tied to a tab (guest-tablet room orders). Optional `?search=` (guest
     * name, tab/order code).
     */
    public function history(Request $request): JsonResponse
    {
        $this->bartender($request);

        $search = trim((string) $request->query('search', ''));

        $tabs = BarTab::with(['bartender', 'openedBy', 'orders'])
            ->where('status', 'settled')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhere('guest_name', 'like', "%{$search}%")
                ->orWhere('table_label', 'like', "%{$search}%")))
            ->latest('id')
            ->limit(100)
            ->get();

        $roomOrders = DiningOrder::forBarLounge()
            ->whereNull('bar_tab_id')
            ->whereIn('status', ['delivered', 'cancelled'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'tabs' => $tabs->map->toBarArray()->values(),
            'room_orders' => $roomOrders->map->toBarOrderArray()->values(),
        ]]);
    }

    /* ---------------- Notifications ---------------- */

    /** GET /bar/notifications — the new-order alert feed, newest first. */
    public function notifications(Request $request): JsonResponse
    {
        $this->bartender($request);

        $notifications = BarNotification::with('diningOrder')->latest()->limit(100)->get();

        return response()->json(['data' => $notifications->map->toBarArray()->values()]);
    }

    /** POST /bar/notifications/{notification}/read */
    public function markNotificationRead(Request $request, int $notification): JsonResponse
    {
        $this->bartender($request);

        $record = BarNotification::find($notification);
        abort_unless($record, 404, 'Notification not found.');

        if (! $record->read_at) {
            $record->update(['read_at' => now()]);
        }

        return response()->json(['data' => $record->toBarArray()]);
    }

    /** POST /bar/notifications/read-all */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->bartender($request);

        BarNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** The authenticated staffer, who must hold the `bar` role. */
    private function bartender(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        abort_unless($user->hasRole('bar'), 403, 'Bar access only.');

        return $user;
    }
}
