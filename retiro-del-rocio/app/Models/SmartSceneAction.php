<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One command a scene fires against one device, in `sort_order`. `command`
 * takes the same shape a direct device command does:
 * {"capability": "power", "value": true}.
 */
class SmartSceneAction extends Model
{
    protected $fillable = [
        'smart_scene_id', 'smart_device_id', 'command', 'sort_order',
    ];

    protected $casts = [
        'command' => 'array',
        'sort_order' => 'integer',
    ];

    public function scene()
    {
        return $this->belongsTo(SmartScene::class, 'smart_scene_id');
    }

    public function device()
    {
        return $this->belongsTo(SmartDevice::class, 'smart_device_id');
    }
}
