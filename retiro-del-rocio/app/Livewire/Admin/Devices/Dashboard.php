<?php

namespace App\Livewire\Admin\Devices;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceActivityLog;
use App\Models\DeviceType;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('device.view'), 403);
    }

    public function render()
    {
        $total = Device::count();
        $threshold = config('devices.battery_alert_threshold');
        $lastSync = Device::max('last_sync_at');

        $cards = [
            ['label' => 'Total Devices', 'value' => $total, 'sub' => 'All device types', 'accent' => '#f38c00'],
            ['label' => 'Online', 'value' => Device::status(DeviceStatus::Online)->count(), 'sub' => 'Live now', 'accent' => '#16a34a'],
            ['label' => 'Offline', 'value' => Device::status(DeviceStatus::Offline)->count(), 'sub' => 'Not reporting', 'accent' => '#6b7280'],
            ['label' => 'Provisioned', 'value' => Device::provisioned()->count(), 'sub' => 'Bound to a room', 'accent' => '#0891b2'],
            ['label' => 'Unassigned', 'value' => Device::unassigned()->count(), 'sub' => 'No room', 'accent' => '#d97706'],
            ['label' => 'In Maintenance', 'value' => Device::status(DeviceStatus::Maintenance)->count(), 'sub' => 'Being serviced', 'accent' => '#b45309'],
            ['label' => 'Battery Alerts', 'value' => Device::whereNotNull('battery_level')->where('battery_level', '<=', $threshold)->count(), 'sub' => 'At or below '.$threshold.'%', 'accent' => '#dc2626'],
            ['label' => 'Last Sync', 'value' => $lastSync ? $lastSync->diffForHumans(short: true) : '—', 'sub' => $lastSync ? $lastSync->format('M j, g:i A') : 'No syncs yet', 'accent' => '#7c3aed'],
        ];

        // Devices by Type
        $byType = DeviceType::query()->withCount('devices')->ordered()->get()
            ->map(fn (DeviceType $t) => ['label' => $t->name, 'value' => $t->devices_count]);

        // Devices by Status (only statuses with devices, keeps the chart tidy)
        $byStatus = collect(DeviceStatus::cases())
            ->map(fn (DeviceStatus $s) => ['label' => $s->label(), 'value' => Device::status($s)->count(), 'hex' => $s->hex()])
            ->filter(fn ($row) => $row['value'] > 0)
            ->values();

        $recent = DeviceActivityLog::query()->with('device')->latest()->limit(12)->get();

        return view('admin.devices.dashboard', [
            'cards' => $cards,
            'byType' => $byType,
            'byTypeMax' => max(1, (int) $byType->max('value')),
            'byStatus' => $byStatus,
            'byStatusTotal' => max(1, (int) $byStatus->sum('value')),
            'recent' => $recent,
        ])->layout('components.admin.app', [
            'title' => 'Device Dashboard',
            'subtitle' => 'Overview of every smart device in the hotel',
        ]);
    }
}
