<?php

namespace App\Livewire\Admin\Vehicles;

use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    /** Vehicle type options offered in the form dropdown. */
    public array $typeOptions = ['Sedan', 'SUV', 'Mini Bus', 'Bus', 'Luxury', 'Van'];

    public string $search = '';

    public string $statusFilter = ''; // '' | available | in_use | maintenance

    public string $sort = 'sort_order'; // sort_order | name | price_desc | price_asc | trips

    /* ----- Add / Edit modal state ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fType = '';

    public $fPrice = '';

    public $fSeats = 4;

    public $fSuitcases = 3;

    public string $fStatus = 'available';

    public bool $fFreeCancellation = true;

    /** Newly uploaded image (temporary), and the current stored path. */
    public $fImage = null;

    public ?string $existingImage = null;

    protected function rules(): array
    {
        return [
            'fName' => ['required', 'string', 'max:120'],
            'fType' => ['nullable', 'string', 'max:60'],
            'fPrice' => ['required', 'integer', 'min:0'],
            'fSeats' => ['required', 'integer', 'min:1', 'max:99'],
            'fSuitcases' => ['required', 'integer', 'min:0', 'max:99'],
            'fStatus' => ['required', Rule::in(['available', 'in_use', 'maintenance'])],
            'fImage' => ['nullable', 'image', 'max:4096'],
        ];
    }

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
        $v = Vehicle::find($id);
        if (! $v) {
            return;
        }

        $this->editingId = $v->id;
        $this->fName = $v->name;
        $this->fType = (string) $v->type;
        $this->fPrice = $v->price;
        $this->fSeats = $v->seats;
        $this->fSuitcases = $v->suitcases;
        $this->fStatus = $v->status;
        $this->fFreeCancellation = $v->free_cancellation;
        $this->existingImage = $v->image;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'fName', 'fType', 'fPrice', 'fSeats', 'fSuitcases', 'fStatus', 'fFreeCancellation', 'fImage', 'existingImage']);
        $this->fSeats = 4;
        $this->fSuitcases = 3;
        $this->fStatus = 'available';
        $this->fFreeCancellation = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        // Keep the current image unless a new one was uploaded.
        $imagePath = $this->existingImage;
        if ($this->fImage) {
            $imagePath = $this->fImage->store('vehicles', 'public');
            \App\Support\ImageOptimizer::optimize($imagePath);

            // Remove the previously uploaded file (never the seeded /public/images assets).
            if ($this->existingImage
                && ! str_starts_with($this->existingImage, 'images/')
                && Storage::disk('public')->exists($this->existingImage)) {
                Storage::disk('public')->delete($this->existingImage);
            }
        }

        $payload = [
            'name' => $data['fName'],
            'type' => $data['fType'] ?: null,
            'price' => (int) $data['fPrice'],
            'seats' => (int) $data['fSeats'],
            'suitcases' => (int) $data['fSuitcases'],
            'status' => $data['fStatus'],
            'free_cancellation' => $this->fFreeCancellation,
            'image' => $imagePath,
        ];

        if ($this->editingId) {
            $v = Vehicle::find($this->editingId);
            if (! $v) {
                return;
            }
            $v->update($payload);
            $message = $v->name.' was updated.';
        } else {
            $payload['slug'] = $this->uniqueSlug($data['fName']);
            $payload['sort_order'] = (int) Vehicle::max('sort_order') + 1;
            $v = Vehicle::create($payload);
            $message = $v->name.' was added to the fleet.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /** URL to show in the form/preview: newly chosen file, else the stored image. */
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
        $v = Vehicle::find($id);
        if (! $v) {
            return;
        }

        if ($v->image
            && ! str_starts_with($v->image, 'images/')
            && Storage::disk('public')->exists($v->image)) {
            Storage::disk('public')->delete($v->image);
        }

        $name = $v->name;
        $v->delete();
        $this->dispatch('toast', type: 'success', message: $name.' was removed.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'vehicle';
        $slug = $base;
        $i = 2;
        while (Vehicle::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        $base = Vehicle::query();

        $total = (clone $base)->count();
        $availableCount = (clone $base)->where('status', 'available')->count();
        $inUseCount = (clone $base)->where('status', 'in_use')->count();
        $maintenanceCount = (clone $base)->where('status', 'maintenance')->count();

        // Revenue from completed airport pick-up trips booked on the website.
        $revenue = (int) Booking::query()
            ->whereNotNull('pickup_vehicle')->where('pickup_vehicle', '!=', '')
            ->whereIn('status', ['paid', 'checked_in', 'checked_out'])
            ->get(['pickup_price'])
            ->sum(fn ($b) => (int) preg_replace('/[^0-9]/', '', (string) $b->pickup_price));

        $stats = [
            ['label' => 'Total Vehicles', 'value' => $total, 'sub' => 'Fleet size', 'accent' => '#f38c00'],
            ['label' => 'Available', 'value' => $availableCount, 'sub' => 'Ready to dispatch', 'accent' => '#16a34a'],
            ['label' => 'In Use', 'value' => $inUseCount, 'sub' => 'Currently active', 'accent' => '#7c3aed'],
            ['label' => 'Est. Revenue', 'value' => '₦'.number_format($revenue), 'sub' => 'From completed trips', 'accent' => '#f38c00'],
        ];

        $vehicles = Vehicle::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('type', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($this->sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when(! in_array($this->sort, ['name', 'price_desc', 'price_asc']), fn ($q) => $q->ordered())
            ->get();

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'stats' => $stats,
            'counts' => [
                'all' => $total,
                'available' => $availableCount,
                'in_use' => $inUseCount,
                'maintenance' => $maintenanceCount,
            ],
        ])->layout('components.admin.app', [
            'title' => 'Vehicles',
            'subtitle' => 'Manage the vehicle pickup fleet shown on the website',
        ]);
    }
}
