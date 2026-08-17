<?php

namespace App\Services\IoT\Tuya;

use App\Models\SmartDevice;

/**
 * POST /v1.0/iot-03/devices/{device_id}/commands with body
 * {"commands": [{"code": ..., "value": ...}]}. Takes a SmartDevice + a
 * normalized {capability, value} pair, looks up the capability's Tuya `code`
 * from the device's stored `capabilities` map, validates the value against
 * the stored type/range/enum, and only then builds the Tuya payload —
 * Flutter and the guest API never see a raw Tuya code.
 */
class TuyaCommandService
{
    public function __construct(protected TuyaClient $client) {}

    public function send(SmartDevice $device, string $capability, mixed $value): void
    {
        $spec = $device->capability($capability);

        if (! $spec) {
            throw new TuyaException("Device does not support the '{$capability}' capability.");
        }

        if (! $device->valueIsValidFor($capability, $value)) {
            throw new TuyaException("Value is out of range/enum for the '{$capability}' capability.");
        }

        $this->client->post("/v1.0/iot-03/devices/{$device->provider_device_id}/commands", [
            'commands' => [
                ['code' => $spec['code'], 'value' => $value],
            ],
        ]);
    }
}
