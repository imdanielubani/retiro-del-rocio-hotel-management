<?php

namespace App\Contracts;

use App\Models\SmartDevice;

/**
 * A smart-room device vendor (Tuya today, potentially a second provider
 * later — e.g. a non-Tuya smart-TV API). `SmartDevice::provider` selects the
 * bound implementation via a small container binding in
 * AppServiceProvider::boot(), so a second vendor never requires touching
 * SmartRoomController or the smart_devices schema.
 */
interface SmartDeviceProviderInterface
{
    /**
     * Raw device list from the vendor, ready to be upserted into
     * `smart_devices` keyed on `provider_device_id`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(): array;

    /**
     * Live status (DP values) for one device from the vendor.
     *
     * @return array<string, mixed>
     */
    public function status(SmartDevice $device): array;

    /**
     * Translate a normalized {capability, value} pair to the vendor's DP code
     * and send it. Implementations must validate the capability/value against
     * the device's stored `capabilities` map before calling out.
     */
    public function sendCommand(SmartDevice $device, string $capability, mixed $value): void;
}
