<?php

namespace App\Livewire\Admin\Devices;

use App\Models\Device;
use App\Services\DeviceProvisioningService;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Device $device;

    public function mount(Device $device): void
    {
        abort_unless(auth()->user()?->can('device.view'), 403);
        $this->device = $device->load(['type', 'room', 'roomUnit', 'creator', 'updater']);
    }

    public function render()
    {
        $logs = $this->device->activityLogs()->with('user')->latest()->paginate(15);
        $qrSvg = app(DeviceProvisioningService::class)->qrSvg($this->device);

        return view('admin.devices.show', [
            'device' => $this->device,
            'logs' => $logs,
            'qrSvg' => $qrSvg,
            'currentGuest' => $this->device->currentGuest(),
        ])->layout('components.admin.app', [
            'title' => $this->device->device_name,
            'subtitle' => 'Device details & activity',
        ]);
    }
}
