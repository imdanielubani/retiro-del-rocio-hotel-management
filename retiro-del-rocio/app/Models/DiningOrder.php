<?php

namespace App\Models;

use App\Support\DiningOrderPricer;
use App\Support\Vat;
use Illuminate\Database\Eloquent\Model;

class DiningOrder extends Model
{
    protected $fillable = [
        'booking_id', 'bar_tab_id', 'reference', 'items', 'has_food', 'has_drinks', 'item_count',
        'subtotal', 'vat', 'service_fee', 'total',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'payment_method', 'paid_at',
        'assigned_to', 'age_verified_at', 'age_verified_by',
    ];

    protected $casts = [
        'items' => 'array',
        'has_food' => 'boolean',
        'has_drinks' => 'boolean',
        'item_count' => 'integer',
        'subtotal' => 'integer',
        'vat' => 'integer',
        'service_fee' => 'integer',
        'total' => 'integer',
        'paid_at' => 'datetime',
        'age_verified_at' => 'datetime',
    ];

    /**
     * New orders with a drink and/or food item in them (whether placed from
     * the guest tablet or a staff tablet's own POS) alert the relevant
     * station — a single hook so each notification always fires no matter
     * which flow created the order. A mixed order alerts both stations.
     */
    protected static function booted(): void
    {
        static::created(function (self $order) {
            if ($order->has_drinks) {
                BarNotification::notify(
                    'new_order',
                    'New Bar Order — '.$order->orderCode(),
                    $order->itemsLabel().($order->tableLabel() ? ' ('.$order->tableLabel().')' : ''),
                    $order,
                );
            }

            if ($order->has_food) {
                KitchenNotification::notify(
                    'new_order',
                    'New Kitchen Order — '.$order->orderCode(),
                    $order->itemsLabel().($order->tableLabel() ? ' ('.$order->tableLabel().')' : ''),
                    $order,
                );
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function barTab()
    {
        return $this->belongsTo(BarTab::class);
    }

    public function assignedBartender()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function ageVerifiedBy()
    {
        return $this->belongsTo(User::class, 'age_verified_by');
    }

    /** Orders the Kitchen admin queue handles — anything with a food item. */
    public function scopeForKitchen($query)
    {
        return $query->where('has_food', true);
    }

    /** Orders the Bar & Lounge admin queue (and Bar Tablet board) handles — anything with a drink item. */
    public function scopeForBarLounge($query)
    {
        return $query->where('has_drinks', true);
    }

    /** Comma-separated dish names, e.g. "Pan-Seared Salmon, Wagyu Tenderloin". */
    public function itemsLabel(): string
    {
        return collect($this->items ?? [])->pluck('name')->filter()->implode(', ') ?: 'Dining order';
    }

    /** The payload the guest tablet's confirmation screen renders. */
    public function toGuestConfirmationArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'items_label' => $this->itemsLabel(),
            'item_count' => $this->item_count,
            'subtotal' => (int) $this->subtotal,
            'subtotal_label' => 'NGN '.number_format((int) $this->subtotal),
            'vat' => (int) $this->vat,
            'vat_label' => 'NGN '.number_format((int) $this->vat),
            'service_fee' => (int) $this->service_fee,
            'service_fee_label' => 'NGN '.number_format((int) $this->service_fee),
            'total' => (int) $this->total,
            'total_label' => 'NGN '.number_format((int) $this->total),
            'payment_method' => $this->payment_method,
        ];
    }

    /**
     * The payload the guest tablet's "My Orders" list renders (Figma
     * 130:415) — one row per confirmed/paid order, past or upcoming, with
     * enough per-item detail (`items`) for the guest to reorder with one tap.
     */
    public function toGuestOrderArray(): array
    {
        $active = in_array($this->status, self::ACTIVE_STATUSES, true);
        $eta = $this->etaMinutes();

        return [
            'id' => $this->id,
            'code' => $this->orderCode(),
            'reference' => $this->reference,
            'items' => collect($this->items ?? [])->map(fn (array $i) => [
                'menu_item_id' => $i['menu_item_id'] ?? null,
                'name' => $i['name'] ?? '',
                'price' => (int) ($i['price'] ?? 0),
                'qty' => (int) ($i['qty'] ?? 1),
                'image_url' => $i['image_url'] ?? null,
            ])->values(),
            'items_label' => $this->itemsLabel(),
            'item_count' => $this->item_count,
            'placed_at' => optional($this->created_at)->toIso8601String(),
            'placed_at_label' => optional($this->created_at)->format('D, M j, Y • g:i A'),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_active' => $active,
            'eta_label' => $active && $eta ? 'ETA ~'.$eta.' mins' : null,
            'charged_to_room' => $this->payment_method === 'room_charge',
            'payment_method_label' => $this->payment_method === 'room_charge' ? 'Charged to Room' : 'Paid Online',
            'subtotal_label' => 'NGN '.number_format((int) $this->subtotal),
            'vat_label' => 'NGN '.number_format((int) $this->vat),
            'service_fee_label' => 'NGN '.number_format((int) $this->service_fee),
            'total_label' => 'NGN '.number_format((int) $this->total),
        ];
    }

    /**
     * The order lifecycle steps the guest tablet's progress tracker renders
     * (Received → Preparing → Ready → On Way → Done). Kitchen/dispatch
     * operations to actually move an order through `ready`/`on_way` aren't
     * built yet, so every order today only ever reaches `confirmed` or
     * `preparing` before going straight to `delivered` — the tracker still
     * renders correctly either way, it just won't visibly pause on those
     * steps until that admin work lands.
     */
    public const ACTIVE_STATUSES = ['confirmed', 'preparing', 'ready', 'on_way'];

    /** Longest prep time among this order's snapshotted items, if known. */
    public function etaMinutes(): ?int
    {
        $minutes = collect($this->items ?? [])
            ->pluck('prep_minutes')
            ->filter()
            ->map(fn ($m) => (int) $m);

        return $minutes->isEmpty() ? null : $minutes->max();
    }

    public function totalLabel(): string
    {
        return '₦'.number_format($this->total);
    }

    public function vatLabel(): string
    {
        return '₦'.number_format($this->vat);
    }

    public function subtotalLabel(): string
    {
        return '₦'.number_format($this->subtotal);
    }

    public function serviceFeeLabel(): string
    {
        return '₦'.number_format($this->service_fee);
    }

    /**
     * Human order code shown to the guest and in the admin table, e.g.
     * "ORD-2601-003" — year+month the order was placed, then a zero-padded
     * sequence number (matches the Figma My Orders design).
     */
    public function orderCode(): string
    {
        $stamp = optional($this->created_at)->format('ym') ?? now()->format('ym');

        return 'ORD-'.$stamp.'-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /* --------- Bar Tablet POS --------- */

    /**
     * New/confirmed → preparing. Idempotent. A drinks-only order (no food
     * item) has no preparing stage — a drink isn't cooked, it goes straight
     * from New to Served — so this only applies once {@see $has_food} is true.
     */
    public function markPreparing(): bool
    {
        if (! $this->has_food) {
            return false;
        }

        if (! in_array($this->status, ['pending', 'confirmed'], true)) {
            return false;
        }

        return $this->update(['status' => 'preparing']);
    }

    /** Any open status → delivered ("Served" on the Bar Tablet). Idempotent. */
    public function markServed(): bool
    {
        if (in_array($this->status, ['delivered', 'cancelled'], true)) {
            return false;
        }

        return $this->update(['status' => 'delivered']);
    }

    /**
     * Where this order sits on the Bar Tablet's New / Preparing / Served
     * board — reuses the existing status vocabulary (no new statuses) so the
     * admin Kitchen/BarLounge `Orders` screens, which already drive every
     * status through this same set, stay fully compatible.
     */
    public function barBoardColumn(): string
    {
        return match ($this->status) {
            'pending', 'confirmed' => 'new',
            'preparing' => 'preparing',
            'ready', 'on_way', 'delivered' => 'served',
            default => 'other',
        };
    }

    public function barBoardColumnLabel(): string
    {
        return match ($this->barBoardColumn()) {
            'new' => 'New',
            'preparing' => 'Preparing',
            'served' => 'Served',
            default => $this->statusLabel(),
        };
    }

    /**
     * Void one line item (a bartender rang up the wrong drink) — the item
     * stays in the snapshot for the record but is excluded from totals.
     * Voiding every item on an order cancels it outright.
     */
    public function voidItem(int $index, User $staff, ?string $reason = null): bool
    {
        $items = $this->items ?? [];
        if (! array_key_exists($index, $items) || ($items[$index]['voided'] ?? false)) {
            return false;
        }

        $items[$index]['voided'] = true;
        $items[$index]['voided_reason'] = $reason;
        $items[$index]['voided_by'] = $staff->name;
        $items[$index]['voided_at'] = now()->toIso8601String();

        $active = collect($items)->reject(fn (array $i) => $i['voided'] ?? false);
        $subtotal = (int) $active->sum(fn (array $i) => (int) ($i['price'] ?? 0) * (int) ($i['qty'] ?? 1));
        $itemCount = (int) $active->sum(fn (array $i) => (int) ($i['qty'] ?? 1));
        $vat = Vat::on($subtotal);
        $serviceFee = $active->isEmpty() ? 0 : $this->service_fee;

        $updated = $this->update([
            'items' => $items,
            'subtotal' => $subtotal,
            'item_count' => $itemCount,
            'vat' => $vat,
            'service_fee' => $serviceFee,
            'total' => $subtotal + $vat + $serviceFee,
            'status' => $active->isEmpty() ? 'cancelled' : $this->status,
        ]);

        $this->barTab?->recalculateTotals();

        return $updated;
    }

    public function assignTo(User $bartender): bool
    {
        return $this->update(['assigned_to' => $bartender->id]);
    }

    /** Any non-voided snapshotted item flagged alcoholic. */
    public function requiresAgeVerification(): bool
    {
        return collect($this->items ?? [])
            ->reject(fn (array $i) => $i['voided'] ?? false)
            ->contains(fn (array $i) => $i['is_alcoholic'] ?? false);
    }

    public function verifyAge(User $staff): bool
    {
        return $this->update(['age_verified_at' => now(), 'age_verified_by' => $staff->id]);
    }

    /** "Table 4" for a POS tab order, or null for a guest-tablet room order. */
    public function tableLabel(): ?string
    {
        $tab = $this->relationLoaded('barTab') ? $this->barTab : $this->barTab()->first();

        return $tab?->table_label;
    }

    /** The Bar Tablet's board/detail payload. */
    public function toBarOrderArray(): array
    {
        $tab = $this->relationLoaded('barTab') ? $this->barTab : $this->barTab()->first();
        $bartender = $this->relationLoaded('assignedBartender') ? $this->assignedBartender : $this->assignedBartender()->first();

        return [
            'id' => $this->id,
            'code' => $this->orderCode(),
            'reference' => $this->reference,
            'bar_tab_id' => $this->bar_tab_id,
            'tab_code' => $tab?->code,
            'table_label' => $tab?->table_label,
            'is_vip' => (bool) $tab?->is_vip,
            'guest_name' => $tab?->guest_name ?: $this->customer_name,
            'source' => $this->bar_tab_id ? 'pos' : 'guest_tablet',
            'has_food' => (bool) $this->has_food,
            'items' => collect($this->items ?? [])->map(fn (array $i) => [
                'menu_item_id' => $i['menu_item_id'] ?? null,
                'name' => $i['name'] ?? '',
                'price' => (int) ($i['price'] ?? 0),
                'qty' => (int) ($i['qty'] ?? 1),
                'note' => $i['note'] ?? null,
                'is_alcoholic' => (bool) ($i['is_alcoholic'] ?? false),
                'voided' => (bool) ($i['voided'] ?? false),
            ])->values(),
            'items_label' => $this->itemsLabel(),
            'item_count' => $this->item_count,
            'placed_at_label' => optional($this->created_at)->format('D, M j, Y • g:i A'),
            'placed_at_short' => optional($this->created_at)->diffForHumans(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'board_column' => $this->barBoardColumn(),
            'board_column_label' => $this->barBoardColumnLabel(),
            'subtotal_label' => $this->subtotalLabel(),
            'vat_label' => $this->vatLabel(),
            'service_fee_label' => $this->serviceFeeLabel(),
            'total_label' => $this->totalLabel(),
            'assigned_to_name' => $bartender?->name,
            'requires_age_verification' => $this->requiresAgeVerification(),
            'age_verified' => $this->age_verified_at !== null,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->paymentLabel(),
        ];
    }

    /**
     * The Kitchen Tablet's ticket board/detail payload — same underlying
     * order as {@see toBarOrderArray()}, minus the tab/VIP/age-verification
     * fields that only make sense for the Bar's own POS. "Table/room"
     * identity for a Kitchen ticket comes from whichever of the tab, the
     * room booking, or the guest's own name is actually set, in that order.
     */
    public function toKitchenOrderArray(): array
    {
        $tab = $this->relationLoaded('barTab') ? $this->barTab : $this->barTab()->first();
        $booking = $this->relationLoaded('booking') ? $this->booking : $this->booking()->first();
        $chef = $this->relationLoaded('assignedBartender') ? $this->assignedBartender : $this->assignedBartender()->first();

        $roomLabel = $booking?->roomUnit?->number ?? $booking?->room_name;

        return [
            'id' => $this->id,
            'code' => $this->orderCode(),
            'reference' => $this->reference,
            'table_label' => $tab?->table_label,
            'room_label' => $roomLabel ? 'Room '.$roomLabel : null,
            'guest_name' => $tab?->guest_name ?: $this->customer_name,
            'source' => $this->bar_tab_id ? 'pos' : 'guest_tablet',
            'has_drinks' => (bool) $this->has_drinks,
            'items' => collect($this->items ?? [])->map(fn (array $i) => [
                'menu_item_id' => $i['menu_item_id'] ?? null,
                'name' => $i['name'] ?? '',
                'price' => (int) ($i['price'] ?? 0),
                'qty' => (int) ($i['qty'] ?? 1),
                'note' => $i['note'] ?? null,
                'category' => $i['category'] ?? null,
                'voided' => (bool) ($i['voided'] ?? false),
            ])->values(),
            'items_label' => $this->itemsLabel(),
            'item_count' => $this->item_count,
            'placed_at_label' => optional($this->created_at)->format('D, M j, Y • g:i A'),
            'placed_at_short' => optional($this->created_at)->diffForHumans(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'board_column' => $this->barBoardColumn(),
            'board_column_label' => $this->barBoardColumnLabel(),
            'subtotal_label' => $this->subtotalLabel(),
            'vat_label' => $this->vatLabel(),
            'service_fee_label' => $this->serviceFeeLabel(),
            'total_label' => $this->totalLabel(),
            'assigned_to_name' => $chef?->name,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->paymentLabel(),
        ];
    }

    /* --------- Payments module presenters (shared transaction table) --------- */

    public function txnId(): string
    {
        return 'TXN-'.(1000 + (int) $this->id);
    }

    public function bookingCode(): string
    {
        return $this->orderCode();
    }

    /**
     * "Kitchen" for a food order, "Bar & Lounge" for a drinks-only order —
     * distinct from {@see RestaurantReservation::sourceLabel()}'s "Restaurant"
     * (table bookings, a different concept). A mixed order counts as Kitchen,
     * since food is the primary component and this only affects which
     * ledger bucket a single row's payment lands in.
     */
    public function sourceLabel(): string
    {
        return $this->has_food ? 'Kitchen' : 'Bar & Lounge';
    }

    /**
     * What the guest actually paid — unlike every other Payments-module
     * model, `total` here already bakes in `vat` (see
     * {@see DiningOrderPricer}), so this must NOT add vat again.
     */
    public function totalWithVatLabel(): string
    {
        return $this->totalLabel();
    }

    public function amountLabel(): string
    {
        return $this->totalLabel();
    }

    public function methodLabel(): string
    {
        return match ($this->payment_method) {
            'card' => 'Card',
            'bank', 'bank_transfer' => 'Bank Transfer',
            'ussd' => 'USSD',
            'qr' => 'QR',
            'mobile_money' => 'Mobile Money',
            'eft' => 'EFT',
            'manual' => 'Manual',
            null, '' => 'Paystack',
            default => ucwords(str_replace('_', ' ', (string) $this->payment_method)),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'on_way' => 'On the Way',
            'pending' => 'Pending',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    /** [text, background] colours for the status badge. */
    public function statusColors(): array
    {
        return match ($this->status) {
            'confirmed', 'preparing', 'ready', 'on_way' => ['#2563eb', '#dbeafe'],
            'delivered' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'cancelled' => ['#dc2626', '#fee2e2'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'refunded' => 'Refunded',
            default => ucfirst((string) $this->payment_status),
        };
    }

    public function paymentStatusLabel(): string
    {
        return $this->paymentLabel();
    }

    public function paymentStatusBadge(): string
    {
        return match ($this->payment_status) {
            'paid' => 'bg-[#dcfce7] text-[#16a34a]',
            'pending' => 'bg-[#fef3c7] text-[#d97706]',
            'refunded' => 'bg-[#fee2e2] text-[#dc2626]',
            default => 'bg-[#f3f4f6] text-[#6b7280]',
        };
    }
}
