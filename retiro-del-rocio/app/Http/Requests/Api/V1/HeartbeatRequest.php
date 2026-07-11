<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DeviceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by auth:sanctum (device token) on the route
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'wifi_strength' => ['nullable', 'integer', 'between:0,100'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'ip_address' => ['nullable', 'ip'],
            // A tablet may report itself into Maintenance/Updating; otherwise a
            // heartbeat implies Online (enforced in the controller).
            'status' => ['nullable', Rule::in([
                DeviceStatus::Online->value,
                DeviceStatus::Maintenance->value,
                DeviceStatus::Updating->value,
            ])],
        ];
    }
}
