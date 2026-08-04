<?php

namespace App\Livewire\Admin\Maintenance;

use App\Models\WorkOrder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Maintenance → Tasks — a desk view of every work order, mirroring
 * what the maintenance tablet's own Work Orders board shows, so the front
 * office doesn't need a technician's tablet in hand to know what's open,
 * urgent, or past its SLA deadline.
 */
class WorkOrders extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | new | accepted | in_progress | done

    public string $priorityFilter = ''; // '' | low | medium | high | urgent

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'priorityFilter'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function setPriority(string $priority): void
    {
        $this->priorityFilter = $this->priorityFilter === $priority ? '' : $priority;
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return WorkOrder::query()
            ->with(['roomUnit', 'asset', 'assignedTo'])
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when(
                in_array($this->statusFilter, [WorkOrder::NEW, WorkOrder::ACCEPTED, WorkOrder::IN_PROGRESS, WorkOrder::DONE], true),
                fn ($q) => $q->where('status', $this->statusFilter)
            )
            ->when(
                in_array($this->priorityFilter, WorkOrder::PRIORITIES, true),
                fn ($q) => $q->where('priority', $this->priorityFilter)
            );
    }

    public function render()
    {
        $open = WorkOrder::where('status', '!=', WorkOrder::DONE)->get();

        $stats = [
            ['label' => 'Open', 'value' => $open->count(), 'sub' => 'Not yet completed', 'accent' => '#f38c00'],
            ['label' => 'Urgent', 'value' => $open->where('priority', 'urgent')->count(), 'sub' => 'Highest priority, open', 'accent' => '#dc2626'],
            ['label' => 'SLA Breaches', 'value' => $open->filter->isSlaBreached()->count(), 'sub' => 'Past their resolution deadline', 'accent' => '#dc2626'],
            ['label' => 'Completed Today', 'value' => WorkOrder::where('status', WorkOrder::DONE)->whereDate('completed_at', today())->count(), 'sub' => 'Closed out today', 'accent' => '#16a34a'],
        ];

        $orders = $this->baseQuery()
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('id')
            ->paginate(10);

        return view('admin.maintenance.work-orders', [
            'orders' => $orders,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Maintenance Tasks',
            'subtitle' => 'Every work order, urgent and SLA-breached first',
        ]);
    }
}
