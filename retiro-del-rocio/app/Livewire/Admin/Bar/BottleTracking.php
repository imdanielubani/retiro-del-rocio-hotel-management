<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarBottleTracking;
use App\Models\BarInventoryItem;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Bottle Tracking — per-bottle open/unopened state and how much of each is left, for high-value spirits poured by the glass. */
class BottleTracking extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $fItemId = null;

    public string $fBottleSize = '750ml';

    public bool $fOpened = false;

    public $fRemainingPercent = 100;

    protected function rules(): array
    {
        return [
            'fItemId' => ['required', 'integer', 'exists:bar_inventory_items,id'],
            'fBottleSize' => ['required', 'string', 'max:40'],
            'fOpened' => ['boolean'],
            'fRemainingPercent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    protected array $validationAttributes = [
        'fItemId' => 'item',
        'fBottleSize' => 'bottle size',
        'fRemainingPercent' => 'remaining quantity',
    ];

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $bottle = BarBottleTracking::find($id);
        if (! $bottle) {
            return;
        }

        $this->editingId = $bottle->id;
        $this->fItemId = $bottle->bar_inventory_item_id;
        $this->fBottleSize = $bottle->bottle_size;
        $this->fOpened = $bottle->opened;
        $this->fRemainingPercent = $bottle->remaining_percent;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'fItemId']);
        $this->fBottleSize = '750ml';
        $this->fOpened = false;
        $this->fRemainingPercent = 100;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'bar_inventory_item_id' => $data['fItemId'],
            'bottle_size' => $data['fBottleSize'],
            'opened' => $this->fOpened,
            'remaining_percent' => $data['fRemainingPercent'],
        ];

        if ($this->editingId) {
            $bottle = BarBottleTracking::find($this->editingId);
            if (! $bottle) {
                return;
            }
            $bottle->update($payload);
            $message = 'Bottle record updated.';
        } else {
            BarBottleTracking::create($payload);
            $message = 'Bottle record added.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $id): void
    {
        $bottle = BarBottleTracking::find($id);
        if (! $bottle) {
            return;
        }
        $bottle->delete();
        $this->dispatch('toast', type: 'success', message: 'Bottle record removed.');
    }

    public function render()
    {
        $bottles = BarBottleTracking::query()->with('item')->latest('id')->paginate(10);

        return view('admin.bar.bottle-tracking', [
            'bottles' => $bottles,
            'items' => BarInventoryItem::orderBy('name')->get(),
        ])->layout('components.admin.app', [
            'title' => 'Bar Bottle Tracking',
            'subtitle' => 'Open/unopened state and remaining quantity, bottle by bottle',
        ]);
    }
}
