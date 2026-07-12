<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_uuid' => $this->device_uuid,
            'device_code' => $this->device_code,
            'device_name' => $this->device_name,
            'type' => $this->whenLoaded('type', fn () => $this->type->name),
            'mode' => $this->mode->value,
            'role' => $this->role,
            'allocation' => $this->allocationLabel(),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn () => optional($this->room)->name),
            'room_unit_id' => $this->room_unit_id,
            'room_number' => $this->whenLoaded('roomUnit', fn () => optional($this->roomUnit)->number),
            // The room's featured photo from Property Management. It belongs to
            // the device's room, not to a booking, so the tablet fetches it once
            // at launch rather than on every room-status poll.
            'room_image' => $this->whenLoaded('room', fn () => optional($this->room)->featuredUrl()),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'battery_level' => $this->battery_level,
            'wifi_strength' => $this->wifi_strength,
            'serial_number' => $this->serial_number,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'android_version' => $this->android_version,
            'app_version' => $this->app_version,
            'ip_address' => $this->ip_address,
            'mac_address' => $this->mac_address,
            'is_provisioned' => $this->is_provisioned,
            'provisioned_at' => optional($this->provisioned_at)->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)->toIso8601String(),
            'last_sync_at' => optional($this->last_sync_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
