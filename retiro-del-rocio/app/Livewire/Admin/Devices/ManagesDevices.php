<?php

namespace App\Livewire\Admin\Devices;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\DeviceCommandService;
use App\Services\DeviceProvisioningService;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;

/**
 * Shared CRUD + fleet actions for the Tablets and Smart TVs screens. The
 * consuming component supplies the device type and labels; permissions and
 * queries are scoped to that type so the two screens never see each other's
 * devices.
 */
trait ManagesDevices
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $roomFilter = '';

    public bool $showFilters = false;

    /* ----- Register / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fCode = '';

    /* Allocation: guest devices bind to a room number, staff tablets to a role. */
    public string $fMode = 'guest';

    public $fSuiteId = '';        // Room (suite) — for cascading to a room number

    public $fRoomUnitId = '';     // RoomUnit — the actual room number

    public string $fRole = '';    // staff role

    public string $fManufacturer = '';

    public string $fModel = '';

    public string $fSerial = '';

    public string $fNotes = '';

    public string $fStatus = '';

    /* ----- Assign modal ----- */
    public bool $showAssign = false;

    public ?int $assignId = null;

    public string $assignMode = 'guest';

    public $assignSuiteId = '';

    public $assignRoomUnitId = '';

    public string $assignRole = '';

    /* ----- QR modal ----- */
    public bool $showQr = false;

    public ?int $qrId = null;

    /* --------------------------------------------------------------------- */
    /* Contract implemented by the consuming component                        */
    /* --------------------------------------------------------------------- */

    abstract protected function typeSlug(): string;      // 'tablet' | 'smart-tv'

    abstract protected function permPrefix(): string;    // 'device' | 'tv'

    abstract protected function singular(): string;

    abstract protected function plural(): string;

    abstract protected function pageTitle(): string;

    abstract protected function pageSubtitle(): string;

    /** Only tablets can be staff stations; smart TVs are always room-bound. */
    protected function supportsStaffMode(): bool
    {
        return false;
    }

    /** Roles a staff tablet may be locked to. */
    protected function staffRoles(): array
    {
        return array_merge(\Database\Seeders\StaffRolesSeeder::ROLES, ['manager']);
    }

    /* --------------------------------------------------------------------- */
    /* Authorisation                                                          */
    /* --------------------------------------------------------------------- */

    /** view/create/edit/delete are per-type; operational actions are shared. */
    protected function permission(string $action): string
    {
        return in_array($action, ['view', 'create', 'edit', 'delete'], true)
            ? "{$this->permPrefix()}.{$action}"
            : "device.{$action}";
    }

    protected function authorizeAction(string $action): void
    {
        abort_unless(auth()->user()?->can($this->permission($action)), 403);
    }

    public function mount(): void
    {
        $this->authorizeAction('view');
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'roomFilter'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'roomFilter']);
        $this->resetPage();
    }

    /* --------------------------------------------------------------------- */
    /* Register / Edit                                                        */
    /* --------------------------------------------------------------------- */

    public function openCreate(): void
    {
        $this->authorizeAction('create');
        $this->reset(['editingId', 'fName', 'fCode', 'fSuiteId', 'fRoomUnitId', 'fRole', 'fManufacturer', 'fModel', 'fSerial', 'fNotes']);
        $this->fMode = 'guest';
        $this->fStatus = DeviceStatus::Offline->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAction('edit');
        $device = $this->scopedQuery()->findOrFail($id);
        $this->editingId = $device->id;
        $this->fName = $device->device_name;
        $this->fCode = $device->device_code;
        $this->fMode = $device->mode->value;
        $this->fSuiteId = $device->room_id ?: '';
        $this->fRoomUnitId = $device->room_unit_id ?: '';
        $this->fRole = (string) $device->role;
        $this->fManufacturer = (string) $device->manufacturer;
        $this->fModel = (string) $device->model;
        $this->fSerial = (string) $device->serial_number;
        $this->fNotes = (string) $device->notes;
        $this->fStatus = $device->status->value;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAction($this->editingId ? 'edit' : 'create');

        // Smart TVs are always room-bound; only tablets can be staff stations.
        $mode = $this->supportsStaffMode() ? $this->fMode : 'guest';

        $rules = [
            'fName' => ['required', 'string', 'max:160'],
            'fCode' => ['required', 'string', 'max:60', Rule::unique('devices', 'device_code')->ignore($this->editingId)],
            'fManufacturer' => ['nullable', 'string', 'max:120'],
            'fModel' => ['nullable', 'string', 'max:120'],
            'fSerial' => ['nullable', 'string', 'max:120'],
            'fNotes' => ['nullable', 'string', 'max:1000'],
            'fStatus' => ['required', Rule::in(array_map(fn (DeviceStatus $s) => $s->value, DeviceStatus::manuallyAssignable()))],
        ];

        if ($mode === 'staff') {
            $rules['fRole'] = ['required', Rule::in($this->staffRoles())];
        } else {
            $rules['fSuiteId'] = ['nullable', 'exists:rooms,id'];
            $rules['fRoomUnitId'] = ['nullable', Rule::exists('room_units', 'id')
                ->where(fn ($q) => $this->fSuiteId ? $q->where('room_id', $this->fSuiteId) : $q)];
        }

        $data = $this->validate($rules, [], [
            'fName' => 'device name', 'fCode' => 'device code',
            'fRoomUnitId' => 'room number', 'fRole' => 'role', 'fSuiteId' => 'suite',
        ]);

        $payload = [
            'device_name' => $data['fName'],
            'device_code' => $data['fCode'],
            'mode' => $mode,
            'manufacturer' => $data['fManufacturer'] ?: null,
            'model' => $data['fModel'] ?: null,
            'serial_number' => $data['fSerial'] ?: null,
            'notes' => $data['fNotes'] ?: null,
            'status' => $data['fStatus'],
            'updated_by' => auth()->id(),
        ];

        if ($this->editingId) {
            $device = $this->scopedQuery()->findOrFail($this->editingId);
            $device->update($payload);
            $device->log('updated', 'Device details updated.');
        } else {
            $device = Device::create($payload + [
                'device_type_id' => $this->typeId(),
                'created_by' => auth()->id(),
            ]);
            $device->log('created', 'Device registered.');
        }

        // Apply allocation after the record exists.
        if ($mode === 'staff') {
            $device->forceFill(['role' => $data['fRole'], 'room_id' => null, 'room_unit_id' => null])->save();
            $device->log('role_assignment', 'Locked to the '.$data['fRole'].' role.');
        } else {
            $device->forceFill(['role' => null])->save();
            $this->placeInRoomUnit($device, ($data['fRoomUnitId'] ?? '') !== '' ? (int) $data['fRoomUnitId'] : null);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $this->singular().' '.($this->editingId ? 'updated' : 'registered').'.');
    }

    public function delete(int $id): void
    {
        $this->authorizeAction('delete');
        $device = $this->scopedQuery()->find($id);

        if (! $device) {
            return;
        }

        $device->log('deleted', 'Device removed.');
        $device->delete();

        $this->dispatch('toast', type: 'success', message: $this->singular().' deleted.');
    }

    /* --------------------------------------------------------------------- */
    /* Allocation (room number for guest devices, role for staff tablets)     */
    /* --------------------------------------------------------------------- */

    public function openAssign(int $id): void
    {
        $this->authorizeAction('assign');
        $device = $this->scopedQuery()->findOrFail($id);
        $this->assignId = $device->id;
        $this->assignMode = $device->mode->value;
        $this->assignSuiteId = $device->room_id ?: '';
        $this->assignRoomUnitId = $device->room_unit_id ?: '';
        $this->assignRole = (string) $device->role;
        $this->resetValidation();
        $this->showAssign = true;
    }

    public function assign(): void
    {
        $this->authorizeAction('assign');
        $device = $this->scopedQuery()->findOrFail($this->assignId);

        if ($device->isStaff()) {
            $data = $this->validate(['assignRole' => ['required', Rule::in($this->staffRoles())]], [], ['assignRole' => 'role']);
            $device->forceFill(['role' => $data['assignRole']])->save();
            $device->log('role_assignment', 'Re-locked to the '.$data['assignRole'].' role.');
            $message = $device->device_name.' set to the '.$data['assignRole'].' role.';
        } else {
            $data = $this->validate([
                'assignRoomUnitId' => ['required', 'exists:room_units,id'],
            ], [], ['assignRoomUnitId' => 'room number']);
            $this->placeInRoomUnit($device, (int) $data['assignRoomUnitId']);
            $message = $device->device_name.' assigned to '.optional($device->fresh()->roomUnit)?->number.'.';
        }

        $this->showAssign = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function unassign(int $id): void
    {
        $this->authorizeAction('assign');
        $device = $this->scopedQuery()->find($id);

        if (! $device || $device->isStaff() || ! $device->room_unit_id) {
            return;
        }

        $this->placeInRoomUnit($device, null);
        $this->dispatch('toast', type: 'success', message: $device->device_name.' moved to Unassigned.');
    }

    /**
     * Bind a guest device to a room number, vacating any other device of the SAME
     * type already on that number (the "replacement" flow the warning describes).
     * room_id is kept in sync as the denormalised suite for filtering.
     */
    protected function placeInRoomUnit(Device $device, ?int $roomUnitId): void
    {
        if ($roomUnitId) {
            Device::query()->ofType($this->typeSlug())
                ->where('room_unit_id', $roomUnitId)
                ->where('id', '!=', $device->id)
                ->get()
                ->each(function (Device $other) {
                    $other->forceFill(['room_unit_id' => null, 'room_id' => null])->save();
                    $other->log('room_assignment', 'Unassigned — room number reassigned to another device.');
                });
        }

        $unit = $roomUnitId ? RoomUnit::find($roomUnitId) : null;

        if ($device->room_unit_id !== $roomUnitId) {
            $device->forceFill([
                'room_unit_id' => $roomUnitId,
                'room_id' => $unit?->room_id,
            ])->save();
            $device->log(
                'room_assignment',
                $unit ? 'Assigned to room '.$unit->number.'.' : 'Unassigned from room.',
                ['room_unit_id' => $roomUnitId],
            );
        }
    }

    /* --------------------------------------------------------------------- */
    /* Device commands                                                        */
    /* --------------------------------------------------------------------- */

    public function restart(int $id, DeviceCommandService $commands): void
    {
        $this->authorizeAction('restart');
        if ($device = $this->scopedQuery()->find($id)) {
            $commands->restart($device);
            $this->dispatch('toast', type: 'success', message: 'Restart sent to '.$device->device_name.'.');
        }
    }

    public function lock(int $id, DeviceCommandService $commands): void
    {
        $this->authorizeAction('lock');
        if ($device = $this->scopedQuery()->find($id)) {
            $commands->lock($device);
            $this->dispatch('toast', type: 'success', message: $device->device_name.' locked.');
        }
    }

    public function unlock(int $id, DeviceCommandService $commands): void
    {
        $this->authorizeAction('unlock');
        if ($device = $this->scopedQuery()->find($id)) {
            $commands->unlock($device);
            $this->dispatch('toast', type: 'success', message: $device->device_name.' unlocked.');
        }
    }

    public function sync(int $id, DeviceCommandService $commands): void
    {
        $this->authorizeAction('sync');
        if ($device = $this->scopedQuery()->find($id)) {
            $commands->sync($device);
            $this->dispatch('toast', type: 'success', message: $device->device_name.' synced.');
        }
    }

    /* --------------------------------------------------------------------- */
    /* QR provisioning                                                        */
    /* --------------------------------------------------------------------- */

    public function openQr(int $id): void
    {
        $this->authorizeAction('view');
        $this->qrId = $this->scopedQuery()->findOrFail($id)->id;
        $this->showQr = true;
    }

    public function regenerateQr(int $id, DeviceProvisioningService $provisioning): void
    {
        abort_unless(auth()->user()?->hasAnyRole(config('devices.provisioning_roles')), 403);
        if ($device = $this->scopedQuery()->find($id)) {
            $provisioning->regenerateToken($device);
            $this->dispatch('toast', type: 'success', message: 'QR reissued for '.$device->device_name.'.');
        }
    }

    /* --------------------------------------------------------------------- */
    /* Export                                                  */
    /* --------------------------------------------------------------------- */

    public function export()
    {
        $this->authorizeAction('view');

        $rows = $this->filteredQuery()->with(['room', 'roomUnit'])->get();
        $filename = str($this->plural())->slug().'-'.now()->format('Y-m-d').'.csv';

        return Response::streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Device Code', 'Device Name', 'Mode', 'Allocation', 'Suite', 'Category', 'Status', 'Battery', 'WiFi', 'Serial', 'Model', 'Last Seen']);

            foreach ($rows as $device) {
                fputcsv($out, [
                    $device->device_code,
                    $device->device_name,
                    $device->mode->label(),
                    $device->allocationLabel() ?? 'Unassigned',
                    $device->isGuest() ? (optional($device->room)->name ?? '') : '',
                    $device->isGuest() ? (optional($device->room)->type ?? '') : '',
                    $device->status->label(),
                    $device->battery_level !== null ? $device->battery_level.'%' : '',
                    $device->wifi_strength !== null ? $device->wifi_strength.'%' : '',
                    $device->serial_number,
                    $device->model,
                    optional($device->last_seen_at)?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* --------------------------------------------------------------------- */
    /* Queries + render                                                       */
    /* --------------------------------------------------------------------- */

    protected function typeId(): int
    {
        return DeviceType::where('slug', $this->typeSlug())->value('id');
    }

    /** Devices of this component's type only. */
    protected function scopedQuery()
    {
        return Device::query()->ofType($this->typeSlug());
    }

    /** Scoped query with the active search + filters applied. */
    protected function filteredQuery()
    {
        return $this->scopedQuery()
            ->search($this->search)
            ->when($this->statusFilter, fn ($q) => $q->status($this->statusFilter))
            ->when($this->roomFilter !== '', function ($q) {
                $this->roomFilter === 'unassigned'
                    ? $q->unassigned()
                    : $q->where('room_id', $this->roomFilter);
            });
    }

    /** Room numbers of a suite, natural-sorted. */
    protected function unitsForSuite($suiteId)
    {
        return $suiteId
            ? RoomUnit::query()->where('room_id', $suiteId)->orderByRaw('LENGTH(number), number')->get()
            : collect();
    }

    public function render()
    {
        $base = $this->scopedQuery();

        $stats = [
            ['label' => 'Total '.$this->plural(), 'value' => (clone $base)->count(), 'sub' => 'Registered', 'accent' => '#f38c00'],
            ['label' => 'Online', 'value' => (clone $base)->status(DeviceStatus::Online)->count(), 'sub' => 'Live now', 'accent' => '#16a34a'],
            ['label' => 'Unassigned', 'value' => (clone $base)->unassigned()->count(), 'sub' => 'No room / role', 'accent' => '#d97706'],
            ['label' => 'Provisioned', 'value' => (clone $base)->provisioned()->count(), 'sub' => 'Bound & active', 'accent' => '#0891b2'],
        ];

        $devices = $this->filteredQuery()->with(['room', 'roomUnit'])->latest('id')->paginate(10);

        return view('admin.devices.manager', [
            'devices' => $devices,
            'stats' => $stats,
            'suites' => Room::query()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'formRoomUnits' => $this->unitsForSuite($this->fSuiteId),
            'assignRoomUnits' => $this->unitsForSuite($this->assignSuiteId),
            'staffRoles' => $this->staffRoles(),
            'supportsStaff' => $this->supportsStaffMode(),
            'statuses' => DeviceStatus::cases(),
            'assignConflict' => $this->assignRoomUnitId
                ? Device::query()->ofType($this->typeSlug())
                    ->where('room_unit_id', $this->assignRoomUnitId)
                    ->where('id', '!=', $this->assignId)
                    ->first()
                : null,
            'qrDevice' => $this->qrId ? $this->scopedQuery()->find($this->qrId) : null,
            'singular' => $this->singular(),
            'plural' => $this->plural(),
            'permPrefix' => $this->permPrefix(),
        ])->layout('components.admin.app', [
            'title' => $this->pageTitle(),
            'subtitle' => $this->pageSubtitle(),
        ]);
    }
}
