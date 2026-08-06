<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Stock In — receive new stock against an item; every receipt updates the item's stock through BarStockMovement. */
class StockIn extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $fItemId = null;

    public string $fSupplier = '';

    public $fQuantity = 1;

    public $fUnitCost = 0;

    public string $fReference = '';

    public string $fDateReceived = '';

    protected function rules(): array
    {
        return [
            'fItemId' => ['required', 'integer', 'exists:bar_inventory_items,id'],
            'fSupplier' => ['nullable', 'string', 'max:120'],
            'fQuantity' => ['required', 'integer', 'min:1'],
            'fUnitCost' => ['required', 'integer', 'min:0'],
            'fReference' => ['nullable', 'string', 'max:120'],
            'fDateReceived' => ['required', 'date'],
        ];
    }

    protected array $validationAttributes = [
        'fItemId' => 'item',
        'fQuantity' => 'quantity received',
        'fUnitCost' => 'unit cost',
        'fDateReceived' => 'date received',
    ];

    public function mount(): void
    {
        $this->fDateReceived = now()->toDateString();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['fItemId', 'fSupplier', 'fQuantity', 'fUnitCost', 'fReference']);
        $this->fQuantity = 1;
        $this->fUnitCost = 0;
        $this->fDateReceived = now()->toDateString();
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
            'type' => BarStockMovement::IN,
            'quantity' => $data['fQuantity'],
            'unit_cost' => $data['fUnitCost'],
            'reference' => $data['fReference'] ?: null,
            'supplier' => $data['fSupplier'] ?: null,
            'occurred_at' => $data['fDateReceived'],
        ]);

        // A fresh cost price on the receipt is the most current price the bar pays — keep the catalog in step with it.
        if ($data['fUnitCost'] > 0) {
            $item->update(['cost_price' => $data['fUnitCost']]);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Received '.$data['fQuantity'].' × '.$item->name.'.');
    }

    public function render()
    {
        $movements = BarStockMovement::query()
            ->with('item')
            ->where('type', BarStockMovement::IN)
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(10);

        return view('admin.bar.stock-in', [
            'movements' => $movements,
            'items' => BarInventoryItem::orderBy('name')->get(),
        ])->layout('components.admin.app', [
            'title' => 'Bar Stock In',
            'subtitle' => 'Record new stock received from suppliers',
        ]);
    }
}
