<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pickup driver on the hotel's roster. Reception and admin assign a driver to
 * a guest's vehicle pickup; a driver can be marked off-duty so they drop out of
 * the assignable list without being deleted.
 */
class Driver extends Model
{
    use SoftDeletes;

    public const AVAILABLE = 'available';

    public const OFF_DUTY = 'off_duty';

    protected $fillable = [
        'name', 'phone', 'license_no', 'vehicle_details', 'status', 'notes', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Bookings whose vehicle pickup is assigned to this driver. */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'pickup_driver_id');
    }

    /** Only drivers who can currently be assigned. */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::AVAILABLE);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::AVAILABLE;
    }

    public function statusLabel(): string
    {
        return $this->status === self::AVAILABLE ? 'Available' : 'Off duty';
    }

    /** Compact roster payload shared by the reception and admin dropdowns. */
    public function toRosterArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'vehicle_details' => $this->vehicle_details,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
        ];
    }
}
