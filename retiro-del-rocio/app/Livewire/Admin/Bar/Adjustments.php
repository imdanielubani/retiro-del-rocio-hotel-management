<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Stock Adjustments — manual corrections (stock count fixes, write-offs) with a reason and notes, kept in the same ledger as every other movement. */
class Adjustments extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $fItemId = null;

    public string $fDirection = 'increase'; // increase | decrease

    public $fQuantity = 1;

    public string $fReason = '';

    public string $fNotes = '';

    protected function rules(): array
    {
        return [
            'fItemId' => ['required', 'integer', 'exists:bar_inventory_items,id'],
            'fDirection' => ['required', 'in:increase,decrease'],
            'fQuantity' => ['required', 'integer', 'min:1'],
            'fReason' => ['required', 'string', 'max:160'],
            'fNotes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected array $validationAttributes = [
        'fItemId' => 'item',
        'fQuantity' => 'quantity',
        'fReason' => 'adjustment reason',
    ];

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['fItemId', 'fReason', 'fNotes']);
        $this->fDirection = 'increase';
        $this->fQuantity = 1;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $item = BarInventoryItem::find($data['fItemId']);
        if (! $item) {
            return;
        }

        BarStockMovement::create([
            'bar_inventory_item_id' => $item->id,
            'type' => $data['fDirection'] === 'increase' ? BarStockMovement::ADJUSTMENT_INCREASE : BarStockMovement::ADJUSTMENT_DECREASE,
            'quantity' => $data['fQuantity'],
            'reason' => $data['fReason'],
            'notes' => $data['fNotes'] ?: null,
            'occurred_at' => now(),
        ]);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Adjusted '.$item->name.' by '.($data['fDirection'] === 'increase' ? '+' : '-').$data['fQuantity'].'.');
    }

    public function render()
    {
        $movements = BarStockMovement::query()
            ->with('item')
            ->whereIn('type', [BarStockMovement::ADJUSTMENT_INCREASE, BarStockMovement::ADJUSTMENT_DECREASE])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(10);

        return view('admin.bar.adjustments', [
            'movements' => $movements,
            'items' => BarInventoryItem::orderBy('name')->get(),
        ])->layout('components.admin.app', [
            'title' => 'Bar Stock Adjustments',
            'subtitle' => 'Manual stock corrections with a reason on record',
        ]);
    }
}
