<?php

namespace App\Livewire\Admin\Bar;

use App\Models\BarInventoryItem;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Bar Inventory → Inventory Items — the product catalog: add, edit, delete, and see each item's live stock status. */
class Items extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | in_stock | low_stock | out_of_stock

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fCategory = '';

    public string $fSku = '';

    public string $fUnit = 'bottle';

    public string $fBrand = '';

    public string $fSupplier = '';

    public $fCostPrice = 0;

    public $fSellingPrice = 0;

    public $fCurrentStock = 0;

    public $fMinimumStockLevel = 0;

    protected function rules(): array
    {
        return [
            'fName' => ['required', 'string', 'max:160'],
            'fCategory' => ['nullable', 'string', 'max:80'],
            'fSku' => ['nullable', 'string', 'max:80'],
            'fUnit' => ['required', 'in:'.implode(',', BarInventoryItem::UNITS)],
            'fBrand' => ['nullable', 'string', 'max:120'],
            'fSupplier' => ['nullable', 'string', 'max:120'],
            'fCostPrice' => ['required', 'integer', 'min:0'],
            'fSellingPrice' => ['required', 'integer', 'min:0'],
            'fCurrentStock' => ['required', 'integer', 'min:0'],
            'fMinimumStockLevel' => ['required', 'integer', 'min:0'],
        ];
    }

    protected array $validationAttributes = [
        'fName' => 'item name',
        'fCostPrice' => 'cost price',
        'fSellingPrice' => 'selling price',
        'fCurrentStock' => 'current stock',
        'fMinimumStockLevel' => 'minimum stock level',
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
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $item = BarInventoryItem::find($id);
        if (! $item) {
            return;
        }

        $this->editingId = $item->id;
        $this->fName = $item->name;
        $this->fCategory = (string) $item->category;
        $this->fSku = (string) $item->sku;
        $this->fUnit = $item->unit;
        $this->fBrand = (string) $item->brand;
        $this->fSupplier = (string) $item->supplier;
        $this->fCostPrice = $item->cost_price;
        $this->fSellingPrice = $item->selling_price;
        $this->fCurrentStock = $item->current_stock;
        $this->fMinimumStockLevel = $item->minimum_stock_level;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'fName', 'fCategory', 'fSku', 'fBrand', 'fSupplier',
            'fCostPrice', 'fSellingPrice', 'fCurrentStock', 'fMinimumStockLevel',
        ]);
        $this->fUnit = 'bottle';
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'name' => $data['fName'],
            'category' => $data['fCategory'] ?: null,
            'sku' => $data['fSku'] ?: null,
            'unit' => $data['fUnit'],
            'brand' => $data['fBrand'] ?: null,
            'supplier' => $data['fSupplier'] ?: null,
            'cost_price' => $data['fCostPrice'],
            'selling_price' => $data['fSellingPrice'],
            'current_stock' => $data['fCurrentStock'],
            'minimum_stock_level' => $data['fMinimumStockLevel'],
        ];

        if ($this->editingId) {
            $item = BarInventoryItem::find($this->editingId);
            if (! $item) {
                return;
            }
            $item->update($payload);
            $message = $item->name.' was updated.';
        } else {
            $item = BarInventoryItem::create($payload);
            $message = $item->name.' was added.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $id): void
    {
        $item = BarInventoryItem::find($id);
        if (! $item) {
            return;
        }

        $name = $item->name;
        $item->delete();
        $this->dispatch('toast', type: 'success', message: $name.' was removed.');
    }

    public function render()
    {
        $all = BarInventoryItem::all();

        $stats = [
            ['label' => 'Total Items', 'value' => $all->count(), 'sub' => 'In the catalog', 'accent' => '#f38c00'],
            ['label' => 'Low Stock', 'value' => $all->filter->isLowStock()->count(), 'sub' => 'At or below minimum', 'accent' => '#d97706'],
            ['label' => 'Out of Stock', 'value' => $all->filter->isOutOfStock()->count(), 'sub' => 'Nothing on hand', 'accent' => '#dc2626'],
        ];

        $items = BarInventoryItem::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('brand', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter === BarInventoryItem::OUT_OF_STOCK, fn ($q) => $q->where('current_stock', '<=', 0))
            ->when($this->statusFilter === BarInventoryItem::LOW_STOCK, fn ($q) => $q->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock_level'))
            ->when($this->statusFilter === BarInventoryItem::IN_STOCK, fn ($q) => $q->whereColumn('current_stock', '>', 'minimum_stock_level'))
            ->orderBy('name')
            ->paginate(10);

        return view('admin.bar.items', [
            'items' => $items,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Bar Inventory Items',
            'subtitle' => 'The bar\'s product catalog',
        ]);
    }
}
