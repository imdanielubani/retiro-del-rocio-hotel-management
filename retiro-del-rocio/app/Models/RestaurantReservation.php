<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RestaurantReservation extends Model
{
    protected $fillable = [
        'code', 'reference', 'area', 'restaurant_table_id', 'table_label', 'floor', 'occasion',
        'guests', 'reserved_date', 'reserved_time', 'special_request',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'fee', 'vat', 'payment_method', 'paid_at',
    ];

    /** The two floors a lounge guest can pick between. */
    public const FLOORS = ['Rooftop', 'Ground floor'];

    protected $casts = [
        'guests' => 'integer',
        'fee' => 'integer',
        'vat' => 'integer',
        'reserved_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /** RES-482913-RDR */
    public static function makeCode(): string
    {
        do {
            $code = 'RES-'.mt_rand(100000, 999999).'-RDR';
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function feeLabel(): string
    {
        return '₦'.number_format($this->fee);
    }

    /** VAT (7.5%) charged on top of the reservation fee at payment time. */
    public function vatLabel(): string
    {
        return '₦'.number_format((int) $this->vat);
    }

    /** What the guest actually paid: the reservation fee plus its VAT. */
    public function totalWithVatLabel(): string
    {
        return '₦'.number_format((int) $this->fee + (int) $this->vat);
    }

    public function areaLabel(): string
    {
        return $this->area === 'lounge' ? 'Lounge' : 'Table Reservation';
    }

    public function isLounge(): bool
    {
        return $this->area === 'lounge';
    }

    /** Only lounge reservations carry a floor. */
    public function floorLabel(): string
    {
        return $this->floor ?: '—';
    }

    public function guestsLabel(): string
    {
        return $this->guests.' '.Str::plural('Guest', $this->guests);
    }

    /** Reserved time formatted as 12-hour with AM/PM, e.g. "7:00 PM". */
    public function timeLabel(): string
    {
        if (! $this->reserved_time) {
            return '—';
        }
        try {
            return Carbon::createFromFormat('H:i', substr($this->reserved_time, 0, 5))->format('g:i A');
        } catch (\Throwable $e) {
            return $this->reserved_time;
        }
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Confirmed',
            'seated' => 'Seated',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            default => ucfirst((string) $this->status),
        };
    }

    /** [text, background] for the status badge. */
    public function statusColors(): array
    {
        return match ($this->status) {
            'confirmed' => ['#16a34a', '#dcfce7'],
            'seated' => ['#0369a1', '#e0f2fe'],
            'completed' => ['#475569', '#eef2f6'],
            'cancelled' => ['#dc2626', '#fee2e2'],
            'no_show' => ['#d97706', '#fef3c7'],
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
        return 'TXN-'.(3000 + (int) $this->id);
    }

    public function bookingCode(): string
    {
        return $this->code;
    }

    public function sourceLabel(): string
    {
        return 'Restaurant';
    }

    public function amountLabel(): string
    {
        return $this->feeLabel();
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
