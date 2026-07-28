<?php

namespace App\Models;

use App\Http\Controllers\Api\V1\TabletController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest pre-settling their running room folio from the My Bills tablet
 * screen, paid via Paystack.
 *
 * @see TabletController
 */
class BillPayment extends Model
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    protected $fillable = [
        'booking_id', 'reference', 'amount', 'vat', 'status', 'paid_at', 'payment_method',
    ];

    protected $casts = [
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

    public function toGuestConfirmationArray(): array
    {
        $total = (int) $this->amount + (int) $this->vat;

        return [
            'reference' => $this->reference,
            'total' => $total,
            'total_label' => 'NGN '.number_format($total),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
        ];
    }
}
