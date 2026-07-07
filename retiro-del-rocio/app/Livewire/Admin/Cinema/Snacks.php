<?php

namespace App\Livewire\Admin\Cinema;

use App\Models\CinemaSnack;
use Livewire\Component;
use Livewire\WithFileUploads;

class Snacks extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fPrice = '';

    public bool $fActive = true;

    public $fImage;            // new upload

    public ?string $fImagePath = null; // existing path

    protected $validationAttributes = [
        'fName' => 'name', 'fPrice' => 'price', 'fImage' => 'image',
    ];

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fPrice', 'fActive', 'fImage', 'fImagePath']);
        $this->fActive = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = CinemaSnack::findOrFail($id);
        $this->editingId = $s->id;
        $this->fName = $s->name;
        $this->fPrice = $s->price;
        $this->fActive = $s->is_active;
        $this->fImagePath = $s->image;
        $this->reset(['fImage']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fPrice' => ['required', 'integer', 'min:0'],
            'fActive' => ['boolean'],
            'fImage' => ['nullable', 'image', 'max:4096'],
        ]);

        $imagePath = $this->fImage ? $this->fImage->store('snacks', 'public') : $this->fImagePath;
        if ($this->fImage) {
            \App\Support\ImageOptimizer::optimize($imagePath);
        }

        $payload = [
            'name' => $data['fName'],
            'price' => (int) $data['fPrice'],
            'image' => $imagePath,
            'is_active' => $this->fActive,
        ];

        if ($this->editingId) {
            CinemaSnack::findOrFail($this->editingId)->update($payload);
        } else {
            $payload['sort_order'] = (int) CinemaSnack::max('sort_order') + 1;
            CinemaSnack::create($payload);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Snack '.($this->editingId ? 'updated' : 'added').'.');
    }

    public function toggleActive(int $id): void
    {
        $s = CinemaSnack::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        $this->dispatch('toast', type: 'success', message: $s->name.' is now '.($s->is_active ? 'available' : 'hidden').'.');
    }

    public function delete(int $id): void
    {
        CinemaSnack::where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Snack deleted.');
    }

    public function render()
    {
        $base = CinemaSnack::query();
        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();

        $stats = [
            ['label' => 'Total Snacks', 'value' => $total, 'sub' => 'On the menu', 'accent' => '#f38c00'],
            ['label' => 'Available', 'value' => $active, 'sub' => 'Shown on the cinema page', 'accent' => '#16a34a'],
            ['label' => 'Hidden', 'value' => $total - $active, 'sub' => 'Not currently sold', 'accent' => '#6b7280'],
        ];

        $snacks = CinemaSnack::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()->get();

        return view('admin.cinema.snacks', [
            'snacks' => $snacks,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Cinema Snacks',
            'subtitle' => 'Food & drinks shown on the movie booking page',
        ]);
    }
}
