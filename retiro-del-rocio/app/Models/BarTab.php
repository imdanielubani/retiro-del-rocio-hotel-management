<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An open running balance at the bar — the Bar Tablet's POS groups one or
 * more {@see DiningOrder} rows under a tab until the waiter/bartender closes
 * and settles it. A tab either comes from a confirmed
 * {@see RestaurantReservation} (a walk-in who booked a table/lounge ahead of
 * arriving) or is opened directly for a guest already seated at the bar; if
 * settled with "Charge to Room" it also carries the in-house {@see Booking}
 * it was charged against.
 */
class BarTab extends Model
{
    protected $fillable = [
        'code', 'restaurant_reservation_id', 'booking_id', 'table_label', 'guest_name',
        'is_vip', 'assigned_to', 'opened_by',
        'status', 'subtotal', 'vat', 'service_fee', 'total',
        'payment_method', 'payment_status', 'settled_at', 'notes',
    ];

    protected $casts = [
        'is_vip' => 'boolean',
        'subtotal' => 'integer',
        'vat' => 'integer',
        'service_fee' => 'integer',
        'total' => 'integer',
        'settled_at' => 'datetime',
    ];

    public const STATUSES = ['open', 'settled', 'void'];

    public function orders()
    {
        return $this->hasMany(DiningOrder::class)->latest('id');
    }

    public function bartender()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function reservation()
    {
        return $this->belongsTo(RestaurantReservation::class, 'restaurant_reservation_id');
    }

    /** The in-house booking this tab was charged to, if settled "Charge to Room". */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    /** Tab code, e.g. "TAB-482913-RDR". */
    public static function makeCode(): string
    {
        do {
            $code = 'TAB-'.mt_rand(100000, 999999).'-RDR';
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Recompute the tab's cached totals from its non-cancelled orders —
     * called whenever an order is added to the tab or one of its items is
     * voided, so the running balance shown on the tablet never drifts from
     * what's actually been rung up.
     */
    public function recalculateTotals(): void
    {
        $orders = $this->orders()->where('status', '!=', 'cancelled')->get();

        $this->update([
            'subtotal' => (int) $orders->sum('subtotal'),
            'vat' => (int) $orders->sum('vat'),
            'service_fee' => (int) $orders->sum('service_fee'),
            'total' => (int) $orders->sum('total'),
        ]);
    }

    /**
     * Close and settle the tab, cascading payment to every order on it.
     *
     * "Charge to Room" doesn't collect payment now — it stakes a claim on the
     * guest's room folio, settled later at checkout — so it stays
     * `payment_status: pending` and stamps `booking_id` on every order
     * (matching the `room_charge` convention {@see ComputesBookingBill}
     * already reads for Spa/Cinema/Dining) instead of `paid_at`.
     */
    public function settle(string $paymentMethod, ?int $bookingId = null): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        $isRoomCharge = $paymentMethod === 'room_charge';
        $paymentStatus = $isRoomCharge ? 'pending' : 'paid';

        $this->orders()->where('status', '!=', 'cancelled')->update([
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'paid_at' => $isRoomCharge ? null : now(),
            'booking_id' => $isRoomCharge ? $bookingId : null,
        ]);

        return $this->update([
            'status' => 'settled',
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'booking_id' => $isRoomCharge ? $bookingId : null,
            'settled_at' => now(),
        ]);
    }

    public function toggleVip(): bool
    {
        return $this->update(['is_vip' => ! $this->is_vip]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'settled' => 'Settled',
            'void' => 'Void',
            default => 'Open',
        };
    }

    /** [text, background] hex for the status pill. */
    public function statusColors(): array
    {
        return match ($this->status) {
            'settled' => ['#16a34a', '#dcfce7'],
            'void' => ['#dc2626', '#fee2e2'],
            default => ['#2563eb', '#dbeafe'],
        };
    }

    public function totalLabel(): string
    {
        return '₦'.number_format($this->total);
    }

    public function subtotalLabel(): string
    {
        return '₦'.number_format($this->subtotal);
    }

    public function vatLabel(): string
    {
        return '₦'.number_format($this->vat);
    }

    public function serviceFeeLabel(): string
    {
        return '₦'.number_format($this->service_fee);
    }

    /** The Bar Tablet's Tabs list row. */
    public function toBarArray(): array
    {
        $bartender = $this->relationLoaded('bartender') ? $this->bartender : $this->bartender()->first();
        $openedBy = $this->relationLoaded('openedBy') ? $this->openedBy : $this->openedBy()->first();
        $orders = $this->relationLoaded('orders') ? $this->orders : $this->orders()->get();
        $reservation = $this->relationLoaded('reservation') ? $this->reservation : $this->reservation()->first();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'table_label' => $this->table_label,
            'guest_name' => $this->guest_name,
            'is_vip' => $this->is_vip,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'reservation_code' => $reservation?->code,
            'order_count' => $orders->where('status', '!=', 'cancelled')->count(),
            'subtotal_label' => $this->subtotalLabel(),
            'vat_label' => $this->vatLabel(),
            'service_fee_label' => $this->serviceFeeLabel(),
            'total_label' => $this->totalLabel(),
            'bartender_name' => $bartender?->name,
            'opened_by_name' => $openedBy?->name,
            'opened_at_label' => optional($this->created_at)->diffForHumans(),
            'settled_at_label' => optional($this->settled_at)->format('D, M j, Y • g:i A'),
        ];
    }

    /** The Tab Detail screen: the tab plus every order on it. */
    public function toBarDetailArray(): array
    {
        $orders = $this->relationLoaded('orders') ? $this->orders : $this->orders()->get();
        $booking = $this->booking_id
            ? ($this->relationLoaded('booking') ? $this->booking : $this->booking()->first())
            : null;

        return [
            ...$this->toBarArray(),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'charged_room_label' => $booking
                ? ('Room '.($booking->roomUnit?->number ?? $booking->room_name))
                : null,
            'notes' => $this->notes,
            'orders' => $orders->map->toBarOrderArray()->values(),
        ];
    }
}
