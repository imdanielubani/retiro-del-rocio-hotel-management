<?php

namespace App\Services\IoT\Simulated;

use App\Contracts\SmartDeviceProviderInterface;
use App\Models\SmartDevice;

/**
 * A dev/demo provider for `SmartDevice::provider === 'simulated'` — no
 * outbound HTTP call at all, just accepts the command as if the vendor
 * confirmed it. Exists so a room can be given working Smart Room controls
 * before any physical Tuya hardware is linked (see
 * docs/architecture/03-tuya-architecture.md §5 — the provider abstraction
 * exists precisely so a case like this doesn't touch SmartRoomController).
 * Never bind this for `SmartDevice::provider === 'tuya'` rows — only for
 * devices explicitly seeded as simulated.
 */
class SimulatedProvider implements SmartDeviceProviderInterface
{
    public function discover(): array
    {
        return [];
    }

    public function status(SmartDevice $device): array
    {
        return (array) $device->last_state;
    }

    public function sendCommand(SmartDevice $device, string $capability, mixed $value): void
    {
        // No-op: SmartRoomController persists last_state itself after this
        // call returns, exactly as it would for a real vendor confirmation.
    }
}
