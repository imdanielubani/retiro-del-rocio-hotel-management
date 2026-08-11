<?php

namespace App\Livewire\Admin\Kitchen;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\ImageOptimizer;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Menu extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $category = '';

    public string $statusFilter = ''; // '' | active | inactive

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fCategory = '';

    public $fPrice = '';

    public string $fDescription = '';

    public $fPrepMinutes = '';

    public bool $fActive = true;

    public $fImage;

    public ?string $fImagePath = null;

    /* ----- Manage Categories modal ----- */
    public bool $showCategoryManager = false;

    public string $newCategoryName = '';

    protected $validationAttributes = [
        'fName' => 'name', 'fPrice' => 'price', 'fImage' => 'image', 'fCategory' => 'category',
    ];

    public function setCategory(string $c): void
    {
        $this->category = $this->category === $c ? '' : $c;
    }

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fPrice', 'fDescription', 'fPrepMinutes', 'fActive', 'fImage', 'fImagePath']);
        $this->fCategory = MenuCategory::food()->ordered()->value('slug') ?? '';
        $this->fActive = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = MenuItem::food()->findOrFail($id);
        $this->editingId = $item->id;
        $this->fName = $item->name;
        $this->fCategory = $item->category;
        $this->fPrice = $item->price;
        $this->fDescription = (string) $item->description;
        $this->fPrepMinutes = $item->prep_minutes;
        $this->fActive = $item->is_active;
        $this->fImagePath = $item->image;
        $this->reset(['fImage']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $foodSlugs = MenuCategory::food()->pluck('slug')->all();

        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fCategory' => ['required', 'string', 'in:'.implode(',', $foodSlugs)],
            'fPrice' => ['required', 'integer', 'min:0'],
            'fDescription' => ['nullable', 'string', 'max:500'],
            'fPrepMinutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'fActive' => ['boolean'],
            'fImage' => ['nullable', 'image', 'max:4096'],
        ]);

        $imagePath = $this->fImage ? $this->fImage->store('menu-items', 'public') : $this->fImagePath;
        if ($this->fImage) {
            ImageOptimizer::optimize($imagePath);
        }

        $payload = [
            'name' => $data['fName'],
            'category' => $data['fCategory'],
            'price' => (int) $data['fPrice'],
            'description' => $data['fDescription'] ?: null,
            'prep_minutes' => $data['fPrepMinutes'] !== '' ? (int) $data['fPrepMinutes'] : null,
            'image' => $imagePath,
            'is_active' => $this->fActive,
        ];

        if ($this->editingId) {
            $item = MenuItem::findOrFail($this->editingId);
            $payload['slug'] = $item->name === $data['fName'] ? $item->slug : $this->uniqueSlug($data['fName'], $item->id);
            $item->update($payload);
        } else {
            $payload['slug'] = $this->uniqueSlug($data['fName']);
            $payload['sort_order'] = (int) MenuItem::max('sort_order') + 1;
            MenuItem::create($payload);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Menu item '.($this->editingId ? 'updated' : 'added').'.');
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (MenuItem::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function toggleActive(int $id): void
    {
        $item = MenuItem::food()->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        $this->dispatch('toast', type: 'success', message: $item->name.' is now '.($item->is_active ? 'on the menu' : 'hidden').'.');
    }

    public function delete(int $id): void
    {
        MenuItem::food()->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Menu item deleted.');
    }

    /* ----- Manage Categories ----- */

    public function openCategoryManager(): void
    {
        $this->newCategoryName = '';
        $this->resetValidation();
        $this->showCategoryManager = true;
    }

    public function closeCategoryManager(): void
    {
        $this->showCategoryManager = false;
    }

    public function addCategory(): void
    {
        $data = $this->validate([
            'newCategoryName' => ['required', 'string', 'max:60'],
        ], [], ['newCategoryName' => 'category name']);

        $slug = Str::slug($data['newCategoryName']);

        if (MenuCategory::where('slug', $slug)->exists()) {
            $this->addError('newCategoryName', 'That category already exists.');

            return;
        }

        MenuCategory::create([
            'name' => $data['newCategoryName'],
            'slug' => $slug,
            'department' => 'food',
            'sort_order' => (int) MenuCategory::food()->max('sort_order') + 1,
        ]);

        $this->newCategoryName = '';
        $this->dispatch('toast', type: 'success', message: 'Category "'.$data['newCategoryName'].'" added.');
    }

    public function deleteCategory(int $id): void
    {
        $cat = MenuCategory::food()->findOrFail($id);
        $count = $cat->itemCount();

        if ($count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Move or delete the '.$count.' '.Str::plural('dish', $count).' in "'.$cat->name.'" first.');

            return;
        }

        $cat->delete();
        $this->dispatch('toast', type: 'success', message: 'Category "'.$cat->name.'" deleted.');
    }

    public function render()
    {
        $base = MenuItem::query()->food();
        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();

        $stats = [
            ['label' => 'Kitchen Dishes', 'value' => $total, 'sub' => 'Across every food category', 'accent' => '#f38c00'],
            ['label' => 'Available', 'value' => $active, 'sub' => 'Shown on Place Order', 'accent' => '#16a34a'],
            ['label' => 'Hidden', 'value' => $total - $active, 'sub' => 'Not currently on the menu', 'accent' => '#6b7280'],
        ];

        $categories = MenuCategory::food()->ordered()->get();

        $items = MenuItem::query()->food()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()->get();

        return view('admin.kitchen.menu', [
            'items' => $items,
            'stats' => $stats,
            'categories' => $categories,
        ])->layout('components.admin.app', [
            'title' => 'Kitchen Menu',
            'subtitle' => 'Food items shown on the guest tablet\'s Place Order screen',
        ]);
    }
}
