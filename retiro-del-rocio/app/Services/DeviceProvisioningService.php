<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Owns QR provisioning: the payload a tablet needs to bind itself, the QR image,
 * and (re)issuing provision tokens. Keeping this out of the Livewire components
 * lets both the dashboard and the API reuse identical provisioning logic.
 */
class DeviceProvisioningService
{
    /**
     * Data encoded in the provisioning QR. Scanned by the Flutter tablet app,
     * which posts it back to /api/tablets/provision to bind permanently.
     *
     * @return array<string, mixed>
     */
    public function payload(Device $device): array
    {
        return [
            'device_code' => $device->device_code,
            'room_id' => $device->room_id,
            'provision_token' => $device->provision_token,
        ];
    }

    public function payloadJson(Device $device): string
    {
        return (string) json_encode($this->payload($device), JSON_UNESCAPED_SLASHES);
    }

    /** Inline SVG QR (no imagick/GD dependency). */
    public function qrSvg(Device $device, int $size = 220): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($this->payloadJson($device));
    }

    /**
     * Issue a fresh provision token, invalidating any previously printed QR and
     * un-provisioning the device so it must re-bind. Returns the new token.
     */
    public function regenerateToken(Device $device): string
    {
        $token = Str::random(64);

        $device->forceFill([
            'provision_token' => $token,
            'is_provisioned' => false,
            'provisioned_at' => null,
        ])->save();

        $device->log('provision', 'Provision token regenerated — QR reissued.');

        return $token;
    }
}
