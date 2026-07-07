<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaBooking extends Model
{
    protected $fillable = [
        'reference', 'services', 'guests', 'date', 'time', 'special_request',
        'subtotal', 'fees', 'taxes', 'total',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'payment_method', 'paid_at',
    ];

    protected $casts = [
        'services' => 'array',
        'guests' => 'integer',
        'date' => 'date',
        'subtotal' => 'integer',
        'fees' => 'integer',
        'taxes' => 'integer',
        'total' => 'integer',
        'paid_at' => 'datetime',
    ];

    /** Human session code shown in the admin table, e.g. "SP-1041". */
    public function sessionCode(): string
    {
        return 'SP-'.(1000 + (int) $this->id);
    }

    /* --------- Payments module presenters (shared transaction table) --------- */

    /** Transaction reference shown in the Payments module, e.g. TXN-1041. */
    public function txnId(): string
    {
        return 'TXN-'.(1000 + (int) $this->id);
    }

    /** Reference shown alongside the transaction (the spa session code). */
    public function bookingCode(): string
    {
        return $this->sessionCode();
    }

    /** Where this transaction came from (room vs spa) for the Payments table. */
    public function sourceLabel(): string
    {
        return 'Spa';
    }

    public function amountLabel(): string
    {
        return $this->totalLabel();
    }

    /** Human label for the Paystack channel / manual entry. */
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

    /** Payment-centric status label for the Payments table. */
    public function paymentStatusLabel(): string
    {
        return $this->paymentLabel();
    }

    /** Tailwind classes for the payment status pill in the Payments table. */
    public function paymentStatusBadge(): string
    {
        return match ($this->payment_status) {
            'paid' => 'bg-[#dcfce7] text-[#16a34a]',
            'pending' => 'bg-[#fef3c7] text-[#d97706]',
            'refunded' => 'bg-[#fee2e2] text-[#dc2626]',
            default => 'bg-[#f3f4f6] text-[#6b7280]',
        };
    }

    public function totalLabel(): string
    {
        return '₦'.number_format($this->total);
    }

    /** Comma-separated service names, e.g. "Skin Care, Massage". */
    public function servicesLabel(): string
    {
        return collect($this->services ?? [])->pluck('name')->filter()->implode(', ') ?: '—';
    }

    /** First/primary service name for the compact table cell. */
    public function primaryService(): string
    {
        return collect($this->services ?? [])->pluck('name')->filter()->first() ?: '—';
    }

    /** Slug of the first service (used to resolve category/duration metadata). */
    public function primarySlug(): ?string
    {
        return collect($this->services ?? [])->pluck('slug')->filter()->first();
    }

    public function timeLabel(): ?string
    {
        return $this->time;
    }

    /* --------- Booking / session status (confirmed | pending | cancelled) --------- */

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Confirmed',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            // Legacy fallback in case any old row slips through.
            'paid' => 'Confirmed',
            default => ucfirst((string) $this->status),
        };
    }

    /** [text, background] colours for the status badge. */
    public function statusColors(): array
    {
        return match ($this->status) {
            'confirmed', 'paid' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'completed' => ['#7c3aed', '#f3e8ff'],
            'cancelled' => ['#dc2626', '#fee2e2'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    /* --------- Payment status (paid | pending | refunded) --------- */

    public function paymentLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'refunded' => 'Refunded',
            default => ucfirst((string) $this->payment_status),
        };
    }

    /** [text, background] colours for the payment badge. */
    public function paymentColors(): array
    {
        return match ($this->payment_status) {
            'paid' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'refunded' => ['#6b7280', '#f3f4f6'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }
}
