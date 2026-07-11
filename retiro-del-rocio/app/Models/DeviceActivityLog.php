<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceActivityLog extends Model
{
    protected $fillable = [
        'device_id', 'event', 'description', 'meta', 'user_id', 'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return \Illuminate\Support\Str::of($this->event)->replace('_', ' ')->title();
    }
}
