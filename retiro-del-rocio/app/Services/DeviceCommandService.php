<?php

namespace App\Services;

use App\Models\Device;

/**
 * Records device commands issued from the admin dashboard (restart, lock,
 * unlock, sync). Each command is written to the device audit trail; the tablet
 * picks it up on its next heartbeat/poll via the API. Centralising this keeps
 * command semantics identical wherever they are triggered.
 */
class DeviceCommandService
{
    public function restart(Device $device): void
    {
        $device->log('restart', 'Restart command issued from the dashboard.');
    }

    public function lock(Device $device): void
    {
        $device->log('lock', 'Lock command issued from the dashboard.');
    }

    public function unlock(Device $device): void
    {
        $device->log('unlock', 'Unlock command issued from the dashboard.');
    }

    public function sync(Device $device): void
    {
        $device->forceFill(['last_sync_at' => now()])->save();
        $device->log('sync', 'Sync command issued from the dashboard.');
    }
}
