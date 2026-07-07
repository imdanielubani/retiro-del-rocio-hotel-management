<?php

namespace App\Livewire\Admin\Restaurant;

use App\Models\RestaurantReservation;
use App\Models\RestaurantTable;

/**
 * Shared CRUD for the admin Tables + Lounge screens. The consuming component
 * defines `$area` ('dining' | 'lounge') and the title/labels.
 */
trait ManagesRestaurantTables
{
    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fCapacity = 2;

    public string $fShape = 'round';

    public string $fDescription = '';

    public bool $fActive = true;

    protected $validationAttributes = [
        'fName' => 'name', 'fCapacity' => 'capacity',
    ];

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fCapacity', 'fShape', 'fDescription', 'fActive']);
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
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fCapacity' => ['required', 'integer', 'min:1', 'max:50'],
            'fShape' => ['required', 'in:round,square,rectangle'],
            'fDescription' => ['nullable', 'string', 'max:255'],
            'fActive' => ['boolean'],
        ]);

        $payload = [
            'name' => $data['fName'],
            'area' => $this->area,
            'capacity' => (int) $data['fCapacity'],
            'shape' => $data['fShape'],
            'description' => $data['fDescription'] ?: null,
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
    }

    public function toggleActive(int $id): void
    {
        $t = RestaurantTable::where('area', $this->area)->findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        $this->dispatch('toast', type: 'success', message: $t->name.' is now '.($t->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        RestaurantTable::where('area', $this->area)->where('id', $id)->delete();
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
