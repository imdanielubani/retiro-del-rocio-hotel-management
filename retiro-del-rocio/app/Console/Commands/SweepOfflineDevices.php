<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Command;

/**
 * Marks live devices Offline once their last heartbeat is older than the
 * configured timeout. Runs on a schedule (see routes/console.php) and is safe
 * to run manually.
 */
class SweepOfflineDevices extends Command
{
    protected $signature = 'devices:sweep-offline';

    protected $description = 'Mark devices Offline when no heartbeat has arrived within the configured timeout.';

    public function handle(): int
    {
        $cutoff = now()->subSeconds((int) config('devices.heartbeat_timeout'));

        $stale = Device::query()
            ->whereIn('status', [DeviceStatus::Online->value, DeviceStatus::Updating->value])
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $cutoff)
            ->get();

        $stale->each(function (Device $device) {
            $device->forceFill(['status' => DeviceStatus::Offline])->save();
            $device->log('offline', 'No heartbeat within timeout — marked offline.');
        });

        $this->info("Marked {$stale->count()} device(s) offline.");

        return self::SUCCESS;
    }
}
