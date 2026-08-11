<?php

namespace App\Livewire\Admin\Housekeeping;

use App\Models\HousekeepingRequest;
use App\Models\HousekeepingRequestType;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Housekeeping → Request Types — the catalog behind the guest
 * tablet's Service Request tiles. Adding, renaming, or retiring a type here
 * takes effect on the guest app immediately (within its own request-type
 * poll) — no code deploy needed, unlike the hardcoded list this replaced.
 */
class RequestTypes extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fLabel = '';

    public string $fIcon = 'cleaning_services';

    public bool $fGuestVisible = true;

    public bool $fActive = true;

    public int $fSortOrder = 0;

    protected function rules(): array
    {
        return [
            'fLabel' => ['required', 'string', 'max:60'],
            'fIcon' => ['required', 'in:'.implode(',', HousekeepingRequestType::ICONS)],
            'fGuestVisible' => ['boolean'],
            'fActive' => ['boolean'],
            'fSortOrder' => ['integer', 'min:0', 'max:999'],
        ];
    }

    protected array $validationAttributes = [
        'fLabel' => 'label',
        'fIcon' => 'icon',
        'fSortOrder' => 'sort order',
    ];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->fSortOrder = (int) HousekeepingRequestType::max('sort_order') + 1;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $type = HousekeepingRequestType::find($id);
        if (! $type) {
            return;
        }
        $this->editingId = $type->id;
        $this->fLabel = $type->label;
        $this->fIcon = $type->icon;
        $this->fGuestVisible = $type->guest_visible;
        $this->fActive = $type->is_active;
        $this->fSortOrder = $type->sort_order;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'fLabel', 'fIcon', 'fGuestVisible', 'fActive', 'fSortOrder']);
        $this->fIcon = 'cleaning_services';
        $this->fGuestVisible = true;
        $this->fActive = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'label' => $data['fLabel'],
            'icon' => $data['fIcon'],
            'guest_visible' => $this->fGuestVisible,
            'is_active' => $this->fActive,
            'sort_order' => $data['fSortOrder'],
        ];

        if ($this->editingId) {
            $type = HousekeepingRequestType::find($this->editingId);
            if (! $type) {
                return;
            }
            $type->update($payload);
            $message = $type->label.' was updated.';
        } else {
            $payload['key'] = $this->uniqueKey($data['fLabel']);
            $type = HousekeepingRequestType::create($payload);
            $message = $type->label.' was added.';
        }

        HousekeepingRequest::flushTypeCatalogCache();
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleActive(int $id): void
    {
        $type = HousekeepingRequestType::find($id);
        if (! $type) {
            return;
        }
        $type->update(['is_active' => ! $type->is_active]);
        HousekeepingRequest::flushTypeCatalogCache();
        $this->dispatch('toast', type: 'success', message: $type->label.($type->is_active ? ' is now active.' : ' was deactivated.'));
    }

    public function toggleGuestVisible(int $id): void
    {
        $type = HousekeepingRequestType::find($id);
        if (! $type || $type->key === HousekeepingRequest::CHECKOUT_INSPECTION) {
            return;
        }
        $type->update(['guest_visible' => ! $type->guest_visible]);
        HousekeepingRequest::flushTypeCatalogCache();
        $this->dispatch('toast', type: 'success', message: $type->label.($type->guest_visible ? ' now shows on the guest tablet.' : ' was hidden from the guest tablet.'));
    }

    public function delete(int $id): void
    {
        $type = HousekeepingRequestType::find($id);
        if (! $type) {
            return;
        }
        // The reception-raised checkout inspection is structurally load-bearing
        // (checked out flow, guest-history exclusion) — it must stay in the
        // catalog even if an admin never edits it, so it can't be deleted.
        if ($type->key === HousekeepingRequest::CHECKOUT_INSPECTION) {
            $this->dispatch('toast', type: 'error', message: 'Checkout Inspection is used by the checkout flow and cannot be removed.');

            return;
        }
        $label = $type->label;
        $type->delete();
        HousekeepingRequest::flushTypeCatalogCache();
        $this->dispatch('toast', type: 'success', message: $label.' was removed.');
    }

    protected function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'request_type';
        $key = $base;
        $i = 2;
        while (HousekeepingRequestType::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }

    public function render()
    {
        $base = HousekeepingRequestType::query();
        $total = (clone $base)->count();
        $activeCount = (clone $base)->where('is_active', true)->count();
        $guestVisibleCount = (clone $base)->guestVisible()->count();

        $stats = [
            ['label' => 'Total Types', 'value' => $total, 'sub' => 'In the catalog', 'accent' => '#f38c00'],
            ['label' => 'Active', 'value' => $activeCount, 'sub' => 'Selectable now', 'accent' => '#16a34a'],
            ['label' => 'On Guest Tablet', 'value' => $guestVisibleCount, 'sub' => 'Shown to guests', 'accent' => '#3b82f6'],
            ['label' => 'Inactive', 'value' => $total - $activeCount, 'sub' => 'Retired', 'accent' => '#d97706'],
        ];

        $types = HousekeepingRequestType::query()
            ->when($this->search, fn ($q) => $q->where('label', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()
            ->paginate(8);

        return view('admin.housekeeping.request-types', [
            'types' => $types,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Housekeeping Request Types',
            'subtitle' => 'The catalog behind the guest tablet\'s Service Request tiles',
        ]);
    }
}
