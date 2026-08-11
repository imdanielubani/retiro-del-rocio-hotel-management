<?php

namespace App\Livewire\Admin\Housekeeping;

use App\Models\HousekeepingRequest;
use App\Models\LostFoundItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Admin → Housekeeping → Staff Workload — how much each housekeeper and
 * maintenance technician has actually gotten through, so a manager doesn't
 * have to eyeball the Service Requests log and tally it by hand.
 *
 * Housekeeping requests aren't pre-assigned to anyone (any housekeeper can
 * pick up any pending one), so "currently assigned" only makes sense for
 * maintenance, which tracks an explicit assignee through accepted/in_progress.
 * Completed counts are bounded by the active date range; "currently assigned"
 * is a live snapshot regardless of range, since an open work order has no
 * "completed at" to filter on.
 */
class StaffWorkload extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = ''; // '' | housekeeping | maintenance

    public string $range = 'month'; // '' (all time) | today | 7d | 30d | month

    public function updating($name): void
    {
        if (in_array($name, ['search', 'roleFilter', 'range'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    /** [start, end] for the active quick-range, or [null, null] for all time. */
    protected function rangeBounds(): array
    {
        $now = Carbon::now();

        return match ($this->range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [null, null],
        };
    }

    /** One row per housekeeping/maintenance staff member, newest-active-role first then name. */
    protected function allRows(): Collection
    {
        [$start, $end] = $this->rangeBounds();

        // A brand-new install (or a test that only seeds one role) may not
        // have both roles created yet — User::role() throws on an unknown
        // role name rather than returning an empty set, so guard for that.
        $housekeepers = Role::where('name', 'housekeeping')->exists()
            ? User::role('housekeeping')->get()->map(fn (User $u) => ['user' => $u, 'role' => 'housekeeping'])
            : collect();
        $technicians = Role::where('name', 'maintenance')->exists()
            ? User::role('maintenance')->get()->map(fn (User $u) => ['user' => $u, 'role' => 'maintenance'])
            : collect();

        $completedRequests = HousekeepingRequest::whereNotNull('completed_by')
            ->when($start, fn ($q) => $q->whereBetween('completed_at', [$start, $end]))
            ->selectRaw('completed_by, count(*) as total')
            ->groupBy('completed_by')
            ->pluck('total', 'completed_by');

        $completedFaults = WorkOrder::where('status', WorkOrder::DONE)
            ->whereNotNull('assigned_to')
            ->when($start, fn ($q) => $q->whereBetween('completed_at', [$start, $end]))
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $openAssigned = WorkOrder::whereIn('status', [WorkOrder::ACCEPTED, WorkOrder::IN_PROGRESS])
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $itemsLogged = LostFoundItem::whereNotNull('found_by')
            ->when($start, fn ($q) => $q->whereBetween('found_at', [$start, $end]))
            ->selectRaw('found_by, count(*) as total')
            ->groupBy('found_by')
            ->pluck('total', 'found_by');

        return $housekeepers->concat($technicians)
            ->map(fn (array $row) => [
                'id' => $row['user']->id,
                'name' => $row['user']->name,
                'role' => $row['role'],
                'role_label' => $row['role'] === 'housekeeping' ? 'Housekeeping' : 'Maintenance',
                'completed' => $row['role'] === 'housekeeping'
                    ? (int) ($completedRequests[$row['user']->id] ?? 0)
                    : (int) ($completedFaults[$row['user']->id] ?? 0),
                'open_assigned' => $row['role'] === 'maintenance' ? (int) ($openAssigned[$row['user']->id] ?? 0) : null,
                'items_logged' => $row['role'] === 'housekeeping' ? (int) ($itemsLogged[$row['user']->id] ?? 0) : null,
            ])
            ->when($this->roleFilter, fn ($rows) => $rows->where('role', $this->roleFilter))
            ->when($this->search, fn ($rows) => $rows->filter(
                fn ($r) => str_contains(strtolower($r['name']), strtolower($this->search))
            ))
            ->sortByDesc('completed')
            ->values();
    }

    protected function paginateRows(Collection $rows, int $perPage = 8): LengthAwarePaginator
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
        $rows = $this->allRows();

        $stats = [
            ['label' => 'Total Staff', 'value' => $rows->count(), 'sub' => 'Housekeeping + maintenance', 'accent' => '#f38c00'],
            ['label' => 'Housekeeping', 'value' => $rows->where('role', 'housekeeping')->count(), 'sub' => 'Staff on roster', 'accent' => '#3b82f6'],
            ['label' => 'Maintenance', 'value' => $rows->where('role', 'maintenance')->count(), 'sub' => 'Staff on roster', 'accent' => '#7c3aed'],
            ['label' => 'Completed', 'value' => $rows->sum('completed'), 'sub' => 'This period, combined', 'accent' => '#16a34a'],
        ];

        return view('admin.housekeeping.staff-workload', [
            'rows' => $this->paginateRows($rows),
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Staff Workload',
            'subtitle' => 'How much each housekeeper and technician has gotten through',
        ]);
    }
}
