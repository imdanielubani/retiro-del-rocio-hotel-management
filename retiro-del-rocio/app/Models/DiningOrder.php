<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningOrder extends Model
{
    protected $fillable = [
        'booking_id', 'reference', 'items', 'has_food', 'has_drinks', 'item_count',
        'subtotal', 'service_fee', 'total',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'payment_method', 'paid_at',
    ];

    protected $casts = [
        'items' => 'array',
        'has_food' => 'boolean',
        'has_drinks' => 'boolean',
        'item_count' => 'integer',
        'subtotal' => 'integer',
        'service_fee' => 'integer',
        'total' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /** Orders the Kitchen admin queue handles — anything with a food item. */
    public function scopeForKitchen($query)
    {
        return $query->where('has_food', true);
    }

    /** Orders the Bar & Lounge admin queue handles — anything with a drink item. */
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

    /* --------- Payments module presenters (shared transaction table) --------- */

    public function txnId(): string
    {
        return 'TXN-'.(1000 + (int) $this->id);
    }

    public function bookingCode(): string
    {
        return $this->orderCode();
    }

    public function sourceLabel(): string
    {
        return 'Restaurant';
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
