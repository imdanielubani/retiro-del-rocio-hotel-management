<?php

namespace App\Livewire\Admin\SmartRoom;

use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SmartDevice;
use App\Models\SmartScene;
use App\Models\SmartSceneAction;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD scenes/actions for a room or room category template. See
 * docs/architecture/02-smart-room-architecture.md §API (admin).
 */
class Scenes extends Component
{
    use WithPagination;

    public string $scopeFilter = 'all'; // all | room | room_unit

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fSlug = '';

    public string $fIcon = '';

    public string $fScopeType = 'room_unit'; // room | room_unit

    public $fRoomId = '';

    public $fRoomUnitId = '';

    /** @var array<int, array{smart_device_id: int|string, capability: string, value: string}> */
    public array $fActions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('smart-room.view'), 403);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('smart-room.manage'), 403);
    }

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'fName', 'fSlug', 'fIcon', 'fRoomId', 'fRoomUnitId', 'fActions']);
        $this->fScopeType = 'room_unit';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $scene = SmartScene::with('actions')->findOrFail($id);
        $this->editingId = $scene->id;
        $this->fName = $scene->name;
        $this->fSlug = $scene->slug;
        $this->fIcon = (string) $scene->icon;
        $this->fScopeType = $scene->room_id ? 'room' : 'room_unit';
        $this->fRoomId = $scene->room_id ?: '';
        $this->fRoomUnitId = $scene->room_unit_id ?: '';
        $this->fActions = $scene->actions->map(fn (SmartSceneAction $a) => [
            'smart_device_id' => $a->smart_device_id,
            'capability' => array_key_first($a->command) ?? '',
            'value' => (string) ($a->command[array_key_first($a->command) ?? ''] ?? ''),
        ])->values()->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function addAction(): void
    {
        $this->fActions[] = ['smart_device_id' => '', 'capability' => '', 'value' => ''];
    }

    public function removeAction(int $index): void
    {
        unset($this->fActions[$index]);
        $this->fActions = array_values($this->fActions);
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fSlug' => ['required', 'string', 'max:60', 'alpha_dash'],
            'fIcon' => ['nullable', 'string', 'max:60'],
            'fRoomId' => $this->fScopeType === 'room' ? ['required', 'exists:rooms,id'] : ['prohibited'],
            'fRoomUnitId' => $this->fScopeType === 'room_unit' ? ['required', 'exists:room_units,id'] : ['prohibited'],
        ], [], ['fName' => 'name', 'fSlug' => 'slug']);

        $payload = [
            'name' => $data['fName'],
            'slug' => $data['fSlug'],
            'icon' => $data['fIcon'] ?: null,
            'room_id' => $this->fScopeType === 'room' ? $data['fRoomId'] : null,
            'room_unit_id' => $this->fScopeType === 'room_unit' ? $data['fRoomUnitId'] : null,
        ];

        $scene = $this->editingId
            ? tap(SmartScene::findOrFail($this->editingId))->update($payload)
            : SmartScene::create($payload);

        $scene->actions()->delete();

        foreach ($this->fActions as $i => $action) {
            if (empty($action['smart_device_id']) || empty($action['capability']) || $action['value'] === '') {
                continue;
            }

            SmartSceneAction::create([
                'smart_scene_id' => $scene->id,
                'smart_device_id' => $action['smart_device_id'],
                'command' => [$action['capability'] => $this->coerceValue($action['value'])],
                'sort_order' => $i,
            ]);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Scene "'.$scene->name.'" saved.');
    }

    /** Coerce a form-string command value to bool/number where obvious, else keep as string. */
    private function coerceValue(string $value): bool|int|string
    {
        return match (true) {
            in_array(strtolower($value), ['true', 'false'], true) => strtolower($value) === 'true',
            is_numeric($value) => (int) $value,
            default => $value,
        };
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();
        SmartScene::find($id)?->delete();
        $this->dispatch('toast', type: 'success', message: 'Scene deleted.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $scene = SmartScene::find($id);
        if (! $scene) {
            return;
        }

        $scene->update(['is_active' => ! $scene->is_active]);
    }

    public function render()
    {
        $scenes = SmartScene::query()
            ->with(['room', 'roomUnit', 'actions.device'])
            ->when($this->scopeFilter === 'room', fn ($q) => $q->whereNotNull('room_id'))
            ->when($this->scopeFilter === 'room_unit', fn ($q) => $q->whereNotNull('room_unit_id'))
            ->ordered()
            ->paginate(15);

        return view('admin.smart-room.scenes', [
            'scenes' => $scenes,
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'roomUnits' => RoomUnit::query()->orderByRaw('LENGTH(number), number')->get(['id', 'number']),
            'devices' => SmartDevice::query()->orderBy('name')->get(['id', 'name']),
        ])->layout('components.admin.app', [
            'title' => 'Scenes',
            'subtitle' => 'One-tap groups of smart-device actions',
        ]);
    }
}
