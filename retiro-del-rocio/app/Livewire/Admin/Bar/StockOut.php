<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Stock Out — issue stock out for a reason other than a sale (damage, complimentary, transfer, expired). Sales go through Consumption Tracking instead, so every sale stays linked to its order. */
class StockOut extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $fItemId = null;

    public $fQuantity = 1;

    public string $fReason = 'damage';

    public string $fStaffName = '';

    public string $fDate = '';

    protected function rules(): array
    {
        return [
            'fItemId' => ['required', 'integer', 'exists:bar_inventory_items,id'],
            'fQuantity' => ['required', 'integer', 'min:1'],
            'fReason' => ['required', 'in:'.implode(',', BarStockMovement::OUT_REASONS)],
            'fStaffName' => ['nullable', 'string', 'max:120'],
            'fDate' => ['required', 'date'],
        ];
    }

    protected array $validationAttributes = [
        'fItemId' => 'item',
        'fQuantity' => 'quantity issued',
        'fStaffName' => 'staff responsible',
    ];

    public function mount(): void
    {
        $this->fDate = now()->toDateString();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['fItemId', 'fStaffName']);
        $this->fQuantity = 1;
        $this->fReason = 'damage';
        $this->fDate = now()->toDateString();
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
            'type' => BarStockMovement::OUT,
            'quantity' => $data['fQuantity'],
            'reason' => $data['fReason'],
            'staff_name' => $data['fStaffName'] ?: null,
            'occurred_at' => $data['fDate'],
        ]);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Issued '.$data['fQuantity'].' × '.$item->name.'.');
    }

    public function render()
    {
        $movements = BarStockMovement::query()
            ->with('item')
            ->where('type', BarStockMovement::OUT)
            ->where(function ($q) {
                $q->where('reason', '!=', 'sale')->orWhereNull('reason');
            })
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(10);

        return view('admin.bar.stock-out', [
            'movements' => $movements,
            'items' => BarInventoryItem::orderBy('name')->get(),
        ])->layout('components.admin.app', [
            'title' => 'Bar Stock Out',
            'subtitle' => 'Record stock leaving for damage, complimentary use, transfer or expiry',
        ]);
    }
}
