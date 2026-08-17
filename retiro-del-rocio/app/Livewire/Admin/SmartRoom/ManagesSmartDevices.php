<?php

namespace App\Livewire\Admin\SmartRoom;

use App\Contracts\SmartDeviceProviderInterface;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SmartDevice;
use App\Services\IoT\Tuya\TuyaException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * List/search/filter smart devices, rename, assign/unassign a room, toggle
 * active, and "test" (fetch live status). Structural sibling of
 * Admin\Devices\ManagesDevices — see
 * docs/architecture/02-smart-room-architecture.md §API (admin).
 */
class ManagesSmartDevices extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public string $roomFilter = '';

    /* Rename / assign modal */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fSuiteId = '';

    public $fRoomUnitId = '';

    public bool $fIsActive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('smart-room.view'), 403);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'typeFilter', 'statusFilter', 'roomFilter'], true)) {
            $this->resetPage();
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('smart-room.manage'), 403);
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $device = SmartDevice::findOrFail($id);
        $this->editingId = $device->id;
        $this->fName = $device->name;
        $this->fSuiteId = $device->roomUnit?->room_id ?: '';
        $this->fRoomUnitId = $device->room_unit_id ?: '';
        $this->fIsActive = $device->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $device = SmartDevice::findOrFail($this->editingId);

        $data = $this->validate([
            'fName' => ['required', 'string', 'max:160'],
            'fSuiteId' => ['nullable', 'exists:rooms,id'],
            'fRoomUnitId' => ['nullable', Rule::exists('room_units', 'id')
                ->where(fn ($q) => $this->fSuiteId ? $q->where('room_id', $this->fSuiteId) : $q)],
        ], [], ['fName' => 'name', 'fRoomUnitId' => 'room number']);

        $renamed = $device->name !== $data['fName'];
        $newRoomUnitId = ($data['fRoomUnitId'] ?? '') !== '' ? (int) $data['fRoomUnitId'] : null;
        $roomChanged = $device->room_unit_id !== $newRoomUnitId;

        $device->forceFill([
            'name' => $data['fName'],
            'room_unit_id' => $newRoomUnitId,
            'is_active' => $this->fIsActive,
        ])->save();

        if ($renamed) {
            $device->log('renamed', 'Renamed to '.$data['fName'].'.');
        }
        if ($roomChanged) {
            $unit = $newRoomUnitId ? RoomUnit::find($newRoomUnitId) : null;
            $device->log($unit ? 'assigned' : 'unassigned', $unit ? 'Assigned to room '.$unit->number.'.' : 'Unassigned from room.');
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $device->name.' updated.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $device = SmartDevice::find($id);
        if (! $device) {
            return;
        }

        $device->update(['is_active' => ! $device->is_active]);
        $device->log($device->is_active ? 'enabled' : 'disabled', $device->is_active ? 'Device enabled.' : 'Device disabled.');
        $this->dispatch('toast', type: 'success', message: $device->name.' '.($device->is_active ? 'enabled' : 'disabled').'.');
    }

    public function unassign(int $id): void
    {
        $this->authorizeManage();
        $device = SmartDevice::find($id);
        if (! $device || ! $device->room_unit_id) {
            return;
        }

        $device->update(['room_unit_id' => null]);
        $device->log('unassigned', 'Unassigned from room.');
        $this->dispatch('toast', type: 'success', message: $device->name.' moved to Unassigned.');
    }

    /** Fetch live status from the provider — a "test connection" per device. */
    public function testConnection(int $id): void
    {
        $this->authorizeManage();
        $device = SmartDevice::find($id);
        if (! $device) {
            return;
        }

        try {
            $provider = app(SmartDeviceProviderInterface::class, ['provider' => $device->provider]);
            $status = $provider->status($device);
            $device->update(['last_state' => $status, 'status' => 'online', 'last_synced_at' => now()]);
            $device->log('synced', 'Live status fetched.');
            $this->dispatch('toast', type: 'success', message: $device->name.' is reachable.');
        } catch (TuyaException $e) {
            $device->update(['status' => 'offline']);
            $this->dispatch('toast', type: 'error', message: $device->name.' could not be reached: '.$e->getMessage());
        }
    }

    protected function unitsForSuite($suiteId)
    {
        return $suiteId
            ? RoomUnit::query()->where('room_id', $suiteId)->orderByRaw('LENGTH(number), number')->get()
            : collect();
    }

    protected function filteredQuery()
    {
        return SmartDevice::query()
            ->search($this->search)
            ->when($this->typeFilter, fn ($q) => $q->ofType($this->typeFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->roomFilter !== '', function ($q) {
                $this->roomFilter === 'unassigned' ? $q->unassigned() : $q->where('room_unit_id', $this->roomFilter);
            });
    }

    public function render()
    {
        $devices = $this->filteredQuery()->with('roomUnit.room')->orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.smart-room.manager', [
            'devices' => $devices,
            'types' => SmartDevice::query()->distinct()->pluck('type'),
            'suites' => Room::query()->orderBy('name')->get(['id', 'name']),
            'formRoomUnits' => $this->unitsForSuite($this->fSuiteId),
        ])->layout('components.admin.app', [
            'title' => 'Smart Devices',
            'subtitle' => 'Every Tuya-connected light, AC, curtain & TV',
        ]);
    }
}
