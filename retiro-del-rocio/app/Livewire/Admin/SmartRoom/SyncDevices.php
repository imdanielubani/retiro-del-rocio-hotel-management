<?php

namespace App\Livewire\Admin\SmartRoom;

use App\Models\SmartDevice;
use App\Services\IoT\Tuya\TuyaClient;
use App\Services\IoT\Tuya\TuyaDeviceService;
use App\Services\IoT\Tuya\TuyaException;
use Livewire\Component;

/**
 * Calls TuyaDeviceService::discover(), upserts `smart_devices` rows keyed on
 * `provider_device_id`, leaves `room_unit_id` null for anything newly
 * discovered — devices are never auto-assigned to a room. See
 * docs/architecture/02-smart-room-architecture.md §API (admin).
 */
class SyncDevices extends Component
{
    public ?string $lastResult = null;

    public bool $lastResultIsError = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('smart-room.sync'), 403);
    }

    public function sync(TuyaDeviceService $devices): void
    {
        abort_unless(auth()->user()?->can('smart-room.sync'), 403);

        try {
            $raw = $devices->discover();
        } catch (TuyaException $e) {
            $this->lastResult = $e->getMessage();
            $this->lastResultIsError = true;
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($raw as $entry) {
            $providerDeviceId = $entry['id'] ?? $entry['device_id'] ?? null;
            if (! $providerDeviceId) {
                continue;
            }

            $capabilities = [];
            try {
                $capabilities = $devices->fetchCapabilities($providerDeviceId);
            } catch (TuyaException $e) {
                report($e);
            }

            $device = SmartDevice::updateOrCreate(
                ['provider_device_id' => $providerDeviceId],
                [
                    'name' => $entry['name'] ?? $entry['custom_name'] ?? 'Unnamed Device',
                    'type' => $this->inferType($entry),
                    'provider' => 'tuya',
                    'provider_product_id' => $entry['product_id'] ?? null,
                    'capabilities' => $capabilities ?: null,
                    'status' => ($entry['online'] ?? false) ? 'online' : 'offline',
                    'last_synced_at' => now(),
                ],
            );

            $device->wasRecentlyCreated ? $created++ : $updated++;
            $device->log('synced', 'Synced from Tuya discovery.');
        }

        $this->lastResultIsError = false;
        $this->lastResult = "Sync complete: {$created} new device(s), {$updated} updated.";
        $this->dispatch('toast', type: 'success', message: $this->lastResult);
    }

    /** Best-effort category guess from Tuya's category code, until an admin renames/retypes it. */
    private function inferType(array $entry): string
    {
        $category = strtolower($entry['category'] ?? '');

        return match (true) {
            str_contains($category, 'light'), str_contains($category, 'dj') => 'light',
            str_contains($category, 'kt'), str_contains($category, 'ac') => 'ac',
            str_contains($category, 'cl'), str_contains($category, 'curtain') => 'curtain',
            str_contains($category, 'tv') => 'tv',
            default => 'other',
        };
    }

    public function render()
    {
        return view('admin.smart-room.sync', [
            'configured' => app(TuyaClient::class)->isConfigured(),
        ])->layout('components.admin.app', [
            'title' => 'Sync Smart Devices',
            'subtitle' => 'Discover devices from the Tuya project',
        ]);
    }
}
