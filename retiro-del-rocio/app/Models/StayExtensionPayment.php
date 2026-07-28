<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\TabletController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Paystack-paid stay extension raised from a guest's in-room tablet.
 *
 * @see TabletController
 */
class StayExtensionPayment extends Model
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    protected $fillable = [
        'booking_id', 'reference', 'nights', 'new_check_out', 'amount', 'vat', 'status',
        'paid_at', 'payment_method',
    ];

    protected $casts = [
        'new_check_out' => 'date',
        'nights' => 'integer',
        'amount' => 'integer',
        'vat' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCESS;
    }

    /* ---------------- Admin Payments module ---------------- */

    /**
     * The host guest's name, read through the booking so the extension lines up
     * with the guest who paid for it in the Payments table.
     */
    public function getCustomerNameAttribute(): ?string
    {
        return $this->booking?->customer_name;
    }

    /** Transaction reference, e.g. TXN-EXT-0007 — distinct from the room folio's. */
    public function txnId(): string
    {
        return 'TXN-EXT-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** The parent booking's code, so the row points back to the stay it extended. */
    public function bookingCode(): string
    {
        return 'BK-'.str_pad((string) $this->booking_id, 4, '0', STR_PAD_LEFT);
    }

    public function sourceLabel(): string
    {
        return 'Extension';
    }

    public function amountLabel(): string
    {
        return '₦'.number_format((int) $this->amount);
    }

    /** VAT (7.5%) charged on top of the extension amount at payment time. */
    public function vatLabel(): string
    {
        return '₦'.number_format((int) $this->vat);
    }

    /** What the guest actually paid: the extension amount plus its VAT. */
    public function totalWithVatLabel(): string
    {
        return '₦'.number_format((int) $this->amount + (int) $this->vat);
    }

    /** Human label for the Paystack channel the guest paid the extension with. */
    public function methodLabel(): string
    {
        return match ($this->payment_method) {
            'card' => 'Card',
            'bank' => 'Bank',
            'bank_transfer' => 'Bank Transfer',
            'ussd' => 'USSD',
            'qr' => 'QR',
            'mobile_money' => 'Mobile Money',
            'eft' => 'EFT',
            null, '' => 'Paystack',
            default => ucwords(str_replace('_', ' ', (string) $this->payment_method)),
        };
    }

    public function paymentStatusLabel(): string
    {
        return $this->isSuccessful() ? 'Paid' : 'Pending';
    }

    public function paymentStatusBadge(): string
    {
        return $this->isSuccessful()
            ? 'bg-[#dcfce7] text-[#16a34a]'
            : 'bg-[#fef3c7] text-[#d97706]';
    }
}
