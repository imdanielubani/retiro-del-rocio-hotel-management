<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CinemaBooking extends Model
{
    protected $fillable = [
        'code', 'reference', 'movie_id', 'movie_title', 'show_date', 'show_time',
        'room', 'guests', 'seats', 'adult_tickets', 'child_tickets', 'snacks',
        'subtotal', 'fee', 'taxes', 'amount',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'payment_method', 'paid_at',
    ];

    protected $casts = [
        'show_date' => 'date',
        'seats' => 'array',
        'snacks' => 'array',
        'guests' => 'integer',
        'adult_tickets' => 'integer',
        'child_tickets' => 'integer',
        'subtotal' => 'integer',
        'fee' => 'integer',
        'taxes' => 'integer',
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    /** Ticket ID, e.g. CIN-482913-RDR */
    public static function makeCode(): string
    {
        do {
            $code = 'CIN-'.mt_rand(100000, 999999).'-RDR';
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function amountLabel(): string
    {
        return '₦'.number_format($this->amount);
    }

    public function subtotalLabel(): string
    {
        return '₦'.number_format($this->subtotal);
    }

    public function feeLabel(): string
    {
        return '₦'.number_format($this->fee);
    }

    public function taxesLabel(): string
    {
        return '₦'.number_format($this->taxes);
    }

    public function seatsLabel(): string
    {
        return collect($this->seats ?? [])->implode(', ') ?: '—';
    }

    public function roomLabel(): string
    {
        return $this->room ?: '—';
    }

    public function guestsLabel(): string
    {
        $g = (int) $this->guests;

        return $g > 0 ? $g.' '.Str::plural('guest', $g) : '—';
    }

    public function ticketTypeLabel(): string
    {
        $parts = [];
        if ($this->adult_tickets) {
            $parts[] = $this->adult_tickets.' '.Str::plural('Adult', $this->adult_tickets);
        }
        if ($this->child_tickets) {
            $parts[] = $this->child_tickets.' '.Str::plural('Child', $this->child_tickets);
        }

        return $parts ? implode(', ', $parts) : '—';
    }

    public function totalTickets(): int
    {
        return (int) $this->adult_tickets + (int) $this->child_tickets;
    }

    public function snacksLabel(): string
    {
        return collect($this->snacks ?? [])
            ->map(fn ($s) => ($s['name'] ?? '').' ×'.($s['qty'] ?? 1))
            ->implode(', ') ?: '—';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Confirmed',
            'used' => 'Used',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusColors(): array
    {
        return match ($this->status) {
            'confirmed' => ['#16a34a', '#dcfce7'],
            'used' => ['#475569', '#eef2f6'],
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

    public function paymentColors(): array
    {
        return match ($this->payment_status) {
            'paid' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'refunded' => ['#dc2626', '#fee2e2'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    /* ---- Payments module presenters (shared transaction table) ---- */

    public function txnId(): string
    {
        return 'TXN-'.(4000 + (int) $this->id);
    }

    public function bookingCode(): string
    {
        return $this->code;
    }

    public function sourceLabel(): string
    {
        return 'Cinema';
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
}
