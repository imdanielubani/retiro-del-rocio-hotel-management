<?php

namespace App\Livewire\Admin\SmartRoom;

use App\Models\RoomUnit;
use App\Models\SmartDevice;
use App\Models\SmartDeviceActivityLog;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('smart-room.view'), 403);
    }

    public function render()
    {
        $total = SmartDevice::count();
        $roomsWithSmartDevices = SmartDevice::whereNotNull('room_unit_id')->distinct('room_unit_id')->count('room_unit_id');
        $totalRooms = RoomUnit::count();

        $cards = [
            ['label' => 'Total Devices', 'value' => $total, 'sub' => 'Discovered from Tuya', 'accent' => '#f38c00'],
            ['label' => 'Online', 'value' => SmartDevice::where('status', 'online')->count(), 'sub' => 'Reporting live', 'accent' => '#16a34a'],
            ['label' => 'Offline', 'value' => SmartDevice::where('status', 'offline')->count(), 'sub' => 'Not reporting', 'accent' => '#6b7280'],
            ['label' => 'Unassigned', 'value' => SmartDevice::unassigned()->count(), 'sub' => 'Awaiting a room', 'accent' => '#d97706'],
            ['label' => 'Room Coverage', 'value' => $totalRooms ? round($roomsWithSmartDevices / $totalRooms * 100).'%' : '—', 'sub' => "{$roomsWithSmartDevices} of {$totalRooms} rooms", 'accent' => '#0891b2'],
        ];

        $byType = SmartDevice::query()
            ->selectRaw('type, count(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type')
            ->map(fn ($c, $type) => ['label' => ucfirst($type), 'value' => $c]);

        $recent = SmartDeviceActivityLog::query()->with('smartDevice')->latest()->limit(12)->get();

        return view('admin.smart-room.dashboard', [
            'cards' => $cards,
            'byType' => $byType->values(),
            'byTypeMax' => max(1, (int) $byType->max('value')),
            'recent' => $recent,
        ])->layout('components.admin.app', [
            'title' => 'Smart Room Dashboard',
            'subtitle' => 'Tuya-connected in-room devices, at a glance',
        ]);
    }
}
