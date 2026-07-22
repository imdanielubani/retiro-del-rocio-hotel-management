<?php

namespace App\Livewire\Admin\Restaurant;

use App\Models\RestaurantReservation;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

/**
 * Shared CRUD for the admin Tables + Lounge screens. The consuming component
 * defines `$area` ('dining' | 'lounge') and the title/labels.
 */
trait ManagesRestaurantTables
{
    use WithFileUploads;

    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fCapacity = 2;

    public string $fShape = 'round';

    public string $fDescription = '';

    public bool $fActive = true;

    /** Newly picked upload (TemporaryUploadedFile), null when untouched. */
    public $fImage = null;

    /** Path already stored on the record, kept when no new file is picked. */
    public ?string $existingImage = null;

    protected $validationAttributes = [
        'fName' => 'name', 'fCapacity' => 'capacity', 'fImage' => 'image',
    ];

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fCapacity', 'fShape', 'fDescription', 'fActive', 'fImage', 'existingImage']);
        $this->fCapacity = 2;
        $this->fShape = 'round';
        $this->fActive = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = RestaurantTable::where('area', $this->area)->findOrFail($id);
        $this->editingId = $t->id;
        $this->fName = $t->name;
        $this->fCapacity = $t->capacity;
        $this->fShape = $t->shape;
        $this->fDescription = (string) $t->description;
        $this->fActive = $t->is_active;
        $this->fImage = null;
        $this->existingImage = $t->image;
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Preview for the modal: the pending upload if any, else what is stored. */
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

    /** Clear the photo so the guest-facing card falls back to the icon. */
    public function removeImage(): void
    {
        $this->fImage = null;
        $this->existingImage = null;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fCapacity' => ['required', 'integer', 'min:1', 'max:50'],
            'fShape' => ['required', 'in:round,square,rectangle'],
            'fDescription' => ['nullable', 'string', 'max:255'],
            'fImage' => ['nullable', 'image', 'max:5120'],
            'fActive' => ['boolean'],
        ]);

        $imagePath = $this->existingImage;

        if ($this->fImage) {
            $imagePath = $this->fImage->store('restaurant', 'public');
            \App\Support\ImageOptimizer::optimize($imagePath);

            // Bin the file we just replaced — but never a seeded /public asset,
            // which is shared and not ours to delete.
            if ($this->existingImage
                && ! str_starts_with($this->existingImage, 'images/')
                && Storage::disk('public')->exists($this->existingImage)) {
                Storage::disk('public')->delete($this->existingImage);
            }
        }

        $payload = [
            'name' => $data['fName'],
            'area' => $this->area,
            'capacity' => (int) $data['fCapacity'],
            'shape' => $data['fShape'],
            'description' => $data['fDescription'] ?: null,
            'image' => $imagePath,
            'is_active' => $this->fActive,
        ];

        if ($this->editingId) {
            RestaurantTable::where('area', $this->area)->findOrFail($this->editingId)->update($payload);
        } else {
            $payload['sort_order'] = (int) RestaurantTable::where('area', $this->area)->max('sort_order') + 1;
            RestaurantTable::create($payload);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $this->singular().' '.($this->editingId ? 'updated' : 'created').'.');
        $this->reset(['fImage', 'existingImage']);
    }

    public function toggleActive(int $id): void
    {
        $t = RestaurantTable::where('area', $this->area)->findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        $this->dispatch('toast', type: 'success', message: $t->name.' is now '.($t->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        $t = RestaurantTable::where('area', $this->area)->where('id', $id)->first();

        if (! $t) {
            return;
        }

        $image = $t->image;
        $t->delete();

        // Drop the uploaded photo with the record; leave seeded assets alone.
        if ($image && ! str_starts_with($image, 'images/') && Storage::disk('public')->exists($image)) {
            Storage::disk('public')->delete($image);
        }

        $this->dispatch('toast', type: 'success', message: $this->singular().' deleted.');
    }

    public function render()
    {
        $base = RestaurantTable::where('area', $this->area);
        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $seats = (int) (clone $base)->where('is_active', true)->sum('capacity');

        $reservations = RestaurantReservation::where('area', $this->area)
            ->whereIn('status', ['confirmed', 'seated'])->count();

        $stats = [
            ['label' => 'Total '.$this->plural(), 'value' => $total, 'sub' => $this->areaLabel().' inventory', 'accent' => '#f38c00'],
            ['label' => 'Active', 'value' => $active, 'sub' => 'Bookable now', 'accent' => '#16a34a'],
            ['label' => 'Total Seats', 'value' => $seats, 'sub' => 'Across active '.strtolower($this->plural()), 'accent' => '#7c3aed'],
            ['label' => 'Upcoming Reservations', 'value' => $reservations, 'sub' => 'Confirmed / seated', 'accent' => '#0891b2'],
        ];

        $items = RestaurantTable::where('area', $this->area)
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()->get();

        return view('admin.restaurant.tables-manager', [
            'items' => $items,
            'stats' => $stats,
            'singular' => $this->singular(),
            'plural' => $this->plural(),
        ])->layout('components.admin.app', [
            'title' => $this->pageTitle(),
            'subtitle' => $this->pageSubtitle(),
        ]);
    }
}
