<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomUnit extends Model
{
    protected $fillable = ['room_id', 'number', 'status', 'booking_id', 'lock_id', 'lock_alias'];

    public function hasLock(): bool
    {
        return filled($this->lock_id);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'occupied' => 'bg-[#fee2e2] text-[#dc2626]',
            'maintenance' => 'bg-[#fef3c7] text-[#d97706]',
            default => 'bg-[#dcfce7] text-[#16a34a]',
        };
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
