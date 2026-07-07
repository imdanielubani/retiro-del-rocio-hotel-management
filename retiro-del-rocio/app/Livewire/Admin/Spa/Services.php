<?php

namespace App\Livewire\Admin\Spa;

use App\Models\SpaBooking;
use App\Models\SpaCategory;
use App\Models\SpaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Services extends Component
{
    use WithFileUploads;

    /** Colour palette offered when creating a category. */
    public array $palette = ['#16a34a', '#3b82f6', '#be185d', '#d97706', '#7c3aed', '#dc2626', '#0d9488', '#f38c00'];

    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    public string $categoryFilter = ''; // '' | category id

    public string $sort = 'sort_order';

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fCategoryId = '';

    public $fPrice = '';

    public $fDuration = '';

    public string $fDescription = '';

    public bool $fActive = true;

    public $fImage = null;

    public ?string $existingImage = null;

    // Inline "new category" creator.
    public bool $fNewCat = false;

    public string $fNewCatName = '';

    public string $fNewCatColor = '#16a34a';

    protected function rules(): array
    {
        return [
            'fName' => ['required', 'string', 'max:120'],
            'fPrice' => ['required', 'integer', 'min:0'],
            'fDuration' => ['nullable', 'integer', 'min:0', 'max:600'],
            'fDescription' => ['nullable', 'string', 'max:500'],
            'fImage' => ['nullable', 'image', 'max:5120'],
            'fCategoryId' => [$this->fNewCat ? 'nullable' : 'required'],
            'fNewCatName' => [$this->fNewCat ? 'required' : 'nullable', 'string', 'max:60'],
            'fNewCatColor' => ['nullable', 'string', 'max:9'],
        ];
    }

    protected array $validationAttributes = [
        'fCategoryId' => 'category', 'fNewCatName' => 'category name',
    ];

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $s = SpaService::find($id);
        if (! $s) {
            return;
        }
        $this->editingId = $s->id;
        $this->fName = $s->name;
        $this->fCategoryId = $s->spa_category_id ?: '';
        $this->fPrice = $s->price;
        $this->fDuration = $s->duration_minutes ?: '';
        $this->fDescription = (string) $s->description;
        $this->fActive = $s->is_active;
        $this->existingImage = $s->image;
        $this->fNewCat = false;
        $this->fNewCatName = '';
        $this->fNewCatColor = '#16a34a';
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'fName', 'fCategoryId', 'fPrice', 'fDuration', 'fDescription', 'fActive', 'fImage', 'existingImage', 'fNewCat', 'fNewCatName', 'fNewCatColor']);
        $this->fActive = true;
        $this->fNewCatColor = '#16a34a';
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        // Resolve / create the category.
        if ($this->fNewCat) {
            $category = SpaCategory::create([
                'name' => trim($data['fNewCatName']),
                'slug' => $this->uniqueCategorySlug($data['fNewCatName']),
                'color' => $this->fNewCatColor ?: '#16a34a',
                'sort_order' => (int) SpaCategory::max('sort_order') + 1,
            ]);
            $categoryId = $category->id;
        } else {
            $categoryId = $data['fCategoryId'] ?: null;
        }

        $imagePath = $this->existingImage;
        if ($this->fImage) {
            $imagePath = $this->fImage->store('spa', 'public');
            \App\Support\ImageOptimizer::optimize($imagePath);
            if ($this->existingImage && ! str_starts_with($this->existingImage, 'images/') && Storage::disk('public')->exists($this->existingImage)) {
                Storage::disk('public')->delete($this->existingImage);
            }
        }

        $payload = [
            'name' => $data['fName'],
            'spa_category_id' => $categoryId,
            'price' => (int) $data['fPrice'],
            'duration_minutes' => $data['fDuration'] !== '' ? (int) $data['fDuration'] : null,
            'description' => $data['fDescription'] ?: null,
            'is_active' => $this->fActive,
            'image' => $imagePath,
        ];

        if ($this->editingId) {
            $s = SpaService::find($this->editingId);
            if (! $s) {
                return;
            }
            $s->update($payload);
            $message = $s->name.' was updated.';
        } else {
            $payload['slug'] = $this->uniqueSlug($data['fName']);
            $payload['sort_order'] = (int) SpaService::max('sort_order') + 1;
            $s = SpaService::create($payload);
            $message = $s->name.' was added.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleActive(int $id): void
    {
        $s = SpaService::find($id);
        if (! $s) {
            return;
        }
        $s->update(['is_active' => ! $s->is_active]);
        $this->dispatch('toast', type: 'success', message: $s->name.($s->is_active ? ' is now active.' : ' was deactivated.'));
    }

    public function imagePreviewUrl(): ?string
    {
        if ($this->fImage) {
            return $this->fImage->temporaryUrl();
        }
        if (! $this->existingImage) {
            return null;
        }
        if (str_starts_with($this->existingImage, 'images/')) {
            return str_replace(' ', '%20', asset($this->existingImage));
        }

        return Storage::disk('public')->url($this->existingImage);
    }

    public function delete(int $id): void
    {
        $s = SpaService::find($id);
        if (! $s) {
            return;
        }
        if ($s->image && ! str_starts_with($s->image, 'images/') && Storage::disk('public')->exists($s->image)) {
            Storage::disk('public')->delete($s->image);
        }
        $name = $s->name;
        $s->delete();
        $this->dispatch('toast', type: 'success', message: $name.' was removed.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $i = 2;
        while (SpaService::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (SpaCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        $base = SpaService::query();
        $total = (clone $base)->count();
        $activeCount = (clone $base)->where('is_active', true)->count();
        $inactiveCount = (clone $base)->where('is_active', false)->count();
        $revenue = (int) SpaBooking::where('payment_status', 'paid')->sum('total');

        $stats = [
            ['label' => 'Total Services', 'value' => $total, 'sub' => 'Spa offerings', 'accent' => '#f38c00', 'subColor' => '#f38c00'],
            ['label' => 'Active', 'value' => $activeCount, 'sub' => 'Currently bookable', 'accent' => '#16a34a', 'subColor' => '#16a34a'],
            ['label' => 'Inactive', 'value' => $inactiveCount, 'sub' => 'Not available', 'accent' => '#dc2626', 'subColor' => '#d97706'],
            ['label' => 'Lifetime Revenue', 'value' => '₦'.number_format($revenue), 'sub' => 'All-time bookings', 'accent' => '#7c3aed', 'subColor' => '#7c3aed'],
        ];

        // Bookings per service (by slug) from paid spa reservations.
        $bookingCounts = [];
        SpaBooking::where('payment_status', 'paid')->get(['services'])->each(function ($b) use (&$bookingCounts) {
            foreach (($b->services ?? []) as $svc) {
                $slug = $svc['slug'] ?? null;
                if ($slug) {
                    $bookingCounts[$slug] = ($bookingCounts[$slug] ?? 0) + 1;
                }
            }
        });

        $services = SpaService::query()->with('category')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->categoryFilter, fn ($q) => $q->where('spa_category_id', $this->categoryFilter))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when(! in_array($this->sort, ['name', 'price_desc', 'price_asc']), fn ($q) => $q->ordered())
            ->get();

        return view('admin.spa.services', [
            'services' => $services,
            'stats' => $stats,
            'counts' => ['all' => $total, 'active' => $activeCount, 'inactive' => $inactiveCount],
            'categories' => SpaCategory::ordered()->get(),
            'bookingCounts' => $bookingCounts,
        ])->layout('components.admin.app', [
            'title' => 'Spa Services',
            'subtitle' => 'Manage the spa & wellness services shown on the website',
        ]);
    }
}
