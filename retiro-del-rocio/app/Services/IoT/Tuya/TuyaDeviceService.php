<?php

namespace App\Services\IoT\Tuya;

use App\Models\SmartDevice;

/**
 * Discovery, detail and specification/capabilities for Tuya devices.
 *
 * `discover()`'s endpoint is confirmed (2026-08-17) against this hotel's live
 * Tuya project: Smart Home PaaS mode, so devices are listed via
 * `GET /v1.0/iot-01/associated-users/devices` (a device is discoverable once
 * it's linked to the associated Tuya app account — not before). An
 * Industry/Custom project uses a different endpoint; re-verify against the
 * console rather than assuming this value if the project type ever changes
 * (docs/architecture/03-tuya-architecture.md §2, §9). Still read from config
 * (`services.tuya.discovery_endpoint` / `services.tuya.discovery_query`)
 * rather than hard-coded, so that re-verification is a config change, not a
 * code change.
 */
class TuyaDeviceService
{
    public function __construct(protected TuyaClient $client) {}

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.tuya.$key", $default);
    }

    /**
     * Raw device list from Tuya, ready to be upserted into `smart_devices`
     * keyed on `provider_device_id`. See class docblock — the endpoint is
     * config-driven and unverified until confirmed against the live account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(): array
    {
        $endpoint = $this->cfg('discovery_endpoint');

        if (blank($endpoint)) {
            throw new TuyaException(
                'Tuya device discovery is not configured. Set TUYA_DISCOVERY_ENDPOINT once '.
                'the project mode (Smart Home PaaS vs. Industry/Custom) is confirmed against '.
                'the live Tuya console — see docs/architecture/03-tuya-architecture.md §2/§9.'
            );
        }

        $result = $this->client->get($endpoint, (array) $this->cfg('discovery_query', []));

        return $result['devices'] ?? $result['list'] ?? [];
    }

    /** GET /v1.0/iot-03/devices/{device_id} — device detail. */
    public function detail(string $providerDeviceId): array
    {
        return $this->client->get("/v1.0/iot-03/devices/{$providerDeviceId}");
    }

    /**
     * GET /v1.0/iot-03/devices/{device_id}/functions — the device's raw
     * capability spec, normalized into the `capabilities` shape stored on
     * `smart_devices` (docs/architecture/02-smart-room-architecture.md
     * §Capability model). Unknown/unsupported function types are dropped
     * rather than guessed at.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchCapabilities(string $providerDeviceId): array
    {
        $result = $this->client->get("/v1.0/iot-03/devices/{$providerDeviceId}/functions");
        $functions = $result['functions'] ?? [];

        $capabilities = [];

        foreach ($functions as $function) {
            $code = $function['code'] ?? null;
            $type = strtolower($function['type'] ?? '');

            if (! $code || ! $type) {
                continue;
            }

            $key = $this->normalizedKey($code);
            $values = json_decode($function['values'] ?? '{}', true) ?: [];

            $capabilities[$key] = match ($type) {
                'boolean' => ['code' => $code, 'type' => 'bool'],
                'integer' => array_filter([
                    'code' => $code,
                    'type' => 'int',
                    'min' => $values['min'] ?? null,
                    'max' => $values['max'] ?? null,
                ], fn ($v) => $v !== null),
                'enum' => [
                    'code' => $code,
                    'type' => 'enum',
                    'values' => $values['range'] ?? [],
                ],
                default => null,
            };

            if ($capabilities[$key] === null) {
                unset($capabilities[$key]);
            }
        }

        return $capabilities;
    }

    /**
     * Map a Tuya DP code to our normalized vocabulary key. Codes we don't
     * recognize are kept as-is (lowercased) so a new device's spec still
     * comes through, even if it isn't one Flutter renders a control for yet.
     */
    protected function normalizedKey(string $tuyaCode): string
    {
        return match ($tuyaCode) {
            'switch', 'switch_led' => 'power',
            'bright_value_v2', 'bright_value' => 'brightness',
            'temp_value_v2', 'temp_value' => 'color_temperature',
            'temp_set' => 'temperature',
            'mode' => 'mode',
            'fan_speed_enum' => 'fan_speed',
            'control' => 'control',
            'percent_control' => 'position',
            default => strtolower($tuyaCode),
        };
    }

    /** Refresh and persist one device's capability map from a live sync. */
    public function refreshCapabilities(SmartDevice $device): SmartDevice
    {
        $device->update([
            'capabilities' => $this->fetchCapabilities($device->provider_device_id),
            'last_synced_at' => now(),
        ]);

        return $device;
    }
}
