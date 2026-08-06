<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use App\Models\BarStockMovement;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Consumption Tracking — stock sold and tied to a guest order. A specialised view over the same stock-out ledger, filtered to reason "sale". */
class Consumption extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $fItemId = null;

    public $fQuantity = 1;

    public string $fLinkedOrder = '';

    public string $fStaffName = '';

    public string $fDate = '';

    protected function rules(): array
    {
        return [
            'fItemId' => ['required', 'integer', 'exists:bar_inventory_items,id'],
            'fQuantity' => ['required', 'integer', 'min:1'],
            'fLinkedOrder' => ['nullable', 'string', 'max:120'],
            'fStaffName' => ['nullable', 'string', 'max:120'],
            'fDate' => ['required', 'date'],
        ];
    }

    protected array $validationAttributes = [
        'fItemId' => 'product',
        'fQuantity' => 'quantity used',
        'fLinkedOrder' => 'linked order',
        'fStaffName' => 'staff',
    ];

    public function mount(): void
    {
        $this->fDate = now()->format('Y-m-d\TH:i');
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['fItemId', 'fLinkedOrder', 'fStaffName']);
        $this->fQuantity = 1;
        $this->fDate = now()->format('Y-m-d\TH:i');
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
            'reason' => 'sale',
            'linked_order' => $data['fLinkedOrder'] ?: null,
            'staff_name' => $data['fStaffName'] ?: null,
            'occurred_at' => $data['fDate'],
        ]);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Logged '.$data['fQuantity'].' × '.$item->name.' consumed.');
    }

    public function render()
    {
        $movements = BarStockMovement::query()
            ->with('item')
            ->where('type', BarStockMovement::OUT)
            ->where('reason', 'sale')
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(10);

        return view('admin.bar.consumption', [
            'movements' => $movements,
            'items' => BarInventoryItem::orderBy('name')->get(),
        ])->layout('components.admin.app', [
            'title' => 'Bar Consumption Tracking',
            'subtitle' => 'Stock sold and tied to a guest order',
        ]);
    }
}
