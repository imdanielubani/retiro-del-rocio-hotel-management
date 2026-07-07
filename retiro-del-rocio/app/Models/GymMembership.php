<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymMembership extends Model
{
    protected $fillable = [
        'code', 'reference', 'gym_plan_id', 'plan_name', 'price', 'period', 'type',
        'customer_name', 'customer_email', 'customer_phone', 'dob',
        'status', 'payment_status', 'starts_at', 'ends_at', 'payment_method', 'paid_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'dob' => 'date',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'paid_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(GymPlan::class, 'gym_plan_id');
    }

    /** Generate a unique membership code, e.g. MP-723653-RDR. */
    public static function makeCode(): string
    {
        do {
            $code = 'MP-'.mt_rand(100000, 999999).'-RDR';
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function priceLabel(): string
    {
        return '₦'.number_format($this->price);
    }

    public function periodShort(): string
    {
        return match ($this->period) {
            'monthly', 'month' => 'month',
            'quarterly', 'quarter' => 'quarter',
            'semi-annually' => '6 months',
            'annually', 'year' => 'year',
            default => $this->period,
        };
    }

    /* ---- Membership status (active | expired | cancelled) ---- */

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    /** [text, background] colours for the status badge. */
    public function statusColors(): array
    {
        return match ($this->status) {
            'active' => ['#16a34a', '#dcfce7'],
            'expired' => ['#d97706', '#fef3c7'],
            'suspended' => ['#475569', '#eef2f6'],
            'cancelled' => ['#dc2626', '#fee2e2'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    /** Months this membership covers (from its stored period). */
    public function durationMonths(): int
    {
        return match ($this->period) {
            'quarterly', 'quarter' => 3,
            'semi-annually' => 6,
            'annually', 'year' => 12,
            default => 1,
        };
    }

    /* ---- Payments module presenters (shared transaction table) ---- */

    public function txnId(): string
    {
        return 'TXN-'.(2000 + (int) $this->id);
    }

    public function bookingCode(): string
    {
        return $this->code;
    }

    public function sourceLabel(): string
    {
        return 'Gym';
    }

    public function amountLabel(): string
    {
        return $this->priceLabel();
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

    /* ---- Payment status (paid | pending | refunded) ---- */

    public function paymentLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'refunded' => 'Refunded',
            default => ucfirst((string) $this->payment_status),
        };
    }

    public function paymentColors(): array
    {
        return match ($this->payment_status) {
            'paid' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'refunded' => ['#6b7280', '#f3f4f6'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    public function typeLabel(): string
    {
        return $this->type === 'renewal' ? 'Renewal' : 'Subscription';
    }
}
