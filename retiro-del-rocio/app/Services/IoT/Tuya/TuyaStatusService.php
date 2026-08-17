<?php

namespace App\Services\IoT\Tuya;

use App\Events\SmartDeviceStatusChanged;
use App\Models\SmartDevice;
use Throwable;

/**
 * GET /v1.0/iot-03/devices/{device_id}/status, used both synchronously
 * (right after a command, to confirm state) and on a scheduled sync (see
 * app/Console/Kernel — every 60-120s per device believed online) to catch
 * state changes made outside our system (a guest using the Tuya/Smart Life
 * app directly, or a physical switch).
 */
class TuyaStatusService
{
    public function __construct(protected TuyaClient $client) {}

    /**
     * Live DP status for one device, keyed by Tuya code, e.g.
     * ['switch_led' => true, 'bright_value_v2' => 800].
     *
     * @return array<string, mixed>
     */
    public function fetch(SmartDevice $device): array
    {
        $result = $this->client->get("/v1.0/iot-03/devices/{$device->provider_device_id}/status");

        $status = [];
        foreach ($result as $dp) {
            if (isset($dp['code'])) {
                $status[$dp['code']] = $dp['value'] ?? null;
            }
        }

        return $status;
    }

    /**
     * Sync one device's status, persist `last_state`/`status`, and broadcast
     * the change. Realtime is an accelerator, never a dependency — a
     * broadcaster outage never fails the sync itself.
     */
    public function sync(SmartDevice $device): SmartDevice
    {
        $rawStatus = $this->fetch($device);

        $device->forceFill([
            'last_state' => $rawStatus,
            'status' => 'online',
            'last_synced_at' => now(),
        ])->save();

        try {
            broadcast(SmartDeviceStatusChanged::forDevice($device));
        } catch (Throwable $e) {
            report($e);
        }

        return $device;
    }
}
