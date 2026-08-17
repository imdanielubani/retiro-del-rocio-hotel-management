<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SmartDeviceActivityLog extends Model
{
    protected $fillable = [
        'smart_device_id', 'event', 'description', 'meta', 'user_id', 'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function smartDevice()
    {
        return $this->belongsTo(SmartDevice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return Str::of($this->event)->replace('_', ' ')->title();
    }
}
