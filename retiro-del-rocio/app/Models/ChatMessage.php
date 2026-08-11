<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message in a guest's Concierge Chat thread with the front desk —
 * scoped to a booking, so a new guest checking into the same room never
 * sees the previous occupant's conversation.
 */
class ChatMessage extends Model
{
    public const GUEST = 'guest';

    public const STAFF = 'staff';

    protected $fillable = [
        'booking_id', 'sender_type', 'sender_name', 'body', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function isFromGuest(): bool
    {
        return $this->sender_type === self::GUEST;
    }

    /** The payload the guest tablet renders in the Concierge Chat thread. */
    public function toGuestArray(): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->sender_name,
            'body' => $this->body,
            'is_mine' => $this->isFromGuest(),
            'created_at' => $this->created_at?->toIso8601String(),
            'time_label' => optional($this->created_at)->format('g:i A'),
        ];
    }

    /** The same thread, from reception's side — `is_mine` flips to the staff reply. */
    public function toStaffArray(): array
    {
        return [
            ...$this->toGuestArray(),
            'is_mine' => ! $this->isFromGuest(),
        ];
    }
}
