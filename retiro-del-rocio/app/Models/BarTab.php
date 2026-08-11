<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An open running balance at the bar — the Bar Tablet's POS groups one or
 * more {@see DiningOrder} rows under a tab until the waiter/bartender closes
 * and settles it. A tab has no room booking (unlike a guest-tablet dining
 * order); it's a walk-in table/seat at the bar.
 */
class BarTab extends Model
{
    protected $fillable = [
        'code', 'table_label', 'guest_name', 'is_vip', 'assigned_to', 'opened_by',
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

    /** Close and settle the tab, cascading payment to every order on it. */
    public function settle(string $paymentMethod): bool
    {
        if ($this->status !== 'open') {
            return false;
        }

        $this->orders()->where('status', '!=', 'cancelled')->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
        ]);

        return $this->update([
            'status' => 'settled',
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
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

        return [
            'id' => $this->id,
            'code' => $this->code,
            'table_label' => $this->table_label,
            'guest_name' => $this->guest_name,
            'is_vip' => $this->is_vip,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
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

        return [
            ...$this->toBarArray(),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'orders' => $orders->map->toBarOrderArray()->values(),
        ];
    }
}
