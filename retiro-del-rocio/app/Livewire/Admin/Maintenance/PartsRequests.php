<?php

namespace App\Livewire\Admin\Maintenance;

use App\Models\PartsRequest;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Maintenance → Service History (parts requests) — every part a
 * technician has asked for, with fulfil/deny actions, mirroring the
 * maintenance tablet's own Requests tab so the desk can approve/deny
 * without borrowing a technician's tablet.
 */
class PartsRequests extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | pending | fulfilled | denied

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

    public function fulfill(int $id): void
    {
        $request = PartsRequest::find($id);
        if (! $request || ! $request->fulfill()) {
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Marked "'.$request->part_name.'" fulfilled.');
    }

    public function deny(int $id): void
    {
        $request = PartsRequest::find($id);
        if (! $request || ! $request->deny()) {
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Denied "'.$request->part_name.'".');
    }

    protected function baseQuery()
    {
        return PartsRequest::query()
            ->with('workOrder.roomUnit.room', 'workOrder.asset')
            ->when($this->search, fn ($q) => $q->where('part_name', 'like', "%{$this->search}%"))
            ->when(
                in_array($this->statusFilter, [PartsRequest::PENDING, PartsRequest::FULFILLED, PartsRequest::DENIED], true),
                fn ($q) => $q->where('status', $this->statusFilter)
            );
    }

    public function render()
    {
        $counts = PartsRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            ['label' => 'Total Requests', 'value' => (int) $counts->sum(), 'sub' => 'All time', 'accent' => '#f38c00'],
            ['label' => 'Pending', 'value' => (int) ($counts['pending'] ?? 0), 'sub' => 'Awaiting a decision', 'accent' => '#d97706'],
            ['label' => 'Fulfilled', 'value' => (int) ($counts['fulfilled'] ?? 0), 'sub' => 'Parts approved', 'accent' => '#16a34a'],
            ['label' => 'Denied', 'value' => (int) ($counts['denied'] ?? 0), 'sub' => 'Parts declined', 'accent' => '#dc2626'],
        ];

        $requests = $this->baseQuery()->latest('id')->paginate(10);

        return view('admin.maintenance.parts-requests', [
            'requests' => $requests,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Maintenance Parts Requests',
            'subtitle' => 'Fulfil or deny what technicians have asked for',
        ]);
    }
}
