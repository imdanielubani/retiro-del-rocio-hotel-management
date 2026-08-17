<?php

namespace App\Services\IoT\Tuya;

use App\Contracts\SmartDeviceProviderInterface;
use App\Models\SmartDevice;

/**
 * Delegates the {@see SmartDeviceProviderInterface} contract to the Tuya
 * service layer. Bound to `provider: 'tuya'` in
 * AppServiceProvider::boot() — see docs/architecture/03-tuya-architecture.md §5.
 */
class TuyaProvider implements SmartDeviceProviderInterface
{
    public function __construct(
        protected TuyaDeviceService $devices,
        protected TuyaStatusService $status,
        protected TuyaCommandService $commands,
    ) {}

    public function discover(): array
    {
        return $this->devices->discover();
    }

    public function status(SmartDevice $device): array
    {
        return $this->status->fetch($device);
    }

    public function sendCommand(SmartDevice $device, string $capability, mixed $value): void
    {
        $this->commands->send($device, $capability, $value);
    }
}
