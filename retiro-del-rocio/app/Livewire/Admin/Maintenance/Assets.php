<?php

namespace App\Livewire\Admin\Maintenance;

use App\Models\Asset;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Maintenance → Assets — the registered-asset roster and its
 * preventive-maintenance state, mirroring the maintenance tablet's own
 * Assets tab.
 */
class Assets extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $dueOnly = false;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'dueOnly'], true)) {
            $this->resetPage();
        }
    }

    public function toggleDueOnly(): void
    {
        $this->dueOnly = ! $this->dueOnly;
        $this->resetPage();
    }

    /** A technician (or admin, standing in when no one's on the tablet) logs a service from the desk. */
    public function markServiced(int $assetId): void
    {
        $asset = Asset::find($assetId);
        if (! $asset) {
            return;
        }

        $asset->update(['last_serviced_at' => now()]);
        $this->dispatch('toast', type: 'success', message: $asset->name.' marked serviced.');
    }

    protected function allRows()
    {
        return Asset::query()
            ->with('roomUnit')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    protected function paginate($rows, int $perPage = 10): LengthAwarePaginator
    {
        $page = $this->getPage();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    public function render()
    {
        $all = $this->allRows();
        $due = $all->filter->isDueForService();
        $rows = $this->dueOnly ? $due->values() : $all;

        $stats = [
            ['label' => 'Registered Assets', 'value' => $all->count(), 'sub' => 'Across the property', 'accent' => '#f38c00'],
            ['label' => 'On a PM Schedule', 'value' => $all->whereNotNull('service_interval_days')->count(), 'sub' => 'Service interval set', 'accent' => '#3b82f6'],
            ['label' => 'Due for Service', 'value' => $due->count(), 'sub' => 'Overdue right now', 'accent' => '#dc2626'],
        ];

        return view('admin.maintenance.assets', [
            'assets' => $this->paginate($rows),
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Maintenance Assets',
            'subtitle' => 'The registered-asset roster and its service schedule',
        ]);
    }
}
