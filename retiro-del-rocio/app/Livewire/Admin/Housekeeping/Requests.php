<?php

namespace App\Livewire\Admin\Housekeeping;

use App\Models\HousekeepingRequest;
use App\Models\WorkOrder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Housekeeping → Service Requests.
 *
 * Every housekeeping ask and maintenance fault a guest has raised from their
 * in-room tablet, combined into one log — the admin mirror of the guest's own
 * Service Request history screen. Defaults to the current month like the
 * Payments module does (an unbounded all-time query would only grow); "All
 * time" is one tap away for genuine full history.
 *
 * Two different Eloquent models feed this, so it can't be a single paginated
 * query — both are fetched (bounded by the active date range), mapped to a
 * common row shape, merged, filtered and sorted in memory, then sliced into a
 * manual paginator.
 */
class Requests extends Component
{
    use WithPagination;

    public string $search = '';

    public string $category = ''; // '' | housekeeping | maintenance

    public string $status = ''; // '' | open | completed

    public string $range = 'month'; // '' (all time) | today | 7d | 30d | month

    public string $from = '';

    public string $to = '';

    public bool $showFilters = false;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'category', 'status', 'range', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'category', 'status', 'range', 'from', 'to']);
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

    /** Every housekeeping + maintenance guest request as one common row shape, newest first. */
    protected function allRows(): Collection
    {
        [$start, $end] = $this->rangeBounds();

        $housekeeping = HousekeepingRequest::with(['roomUnit.room', 'booking', 'completedBy'])
            // Reception's own pre-checkout inspection isn't something the
            // guest asked for — it stays off this log, same as it stays off
            // the guest's own history (see GuestServiceRequestController).
            ->where('type', '!=', HousekeepingRequest::CHECKOUT_INSPECTION)
            ->when($start, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->get()
            ->map(fn (HousekeepingRequest $r) => [
                'uid' => 'hk-'.$r->id,
                'category' => 'housekeeping',
                'category_label' => 'Housekeeping',
                'title' => $r->typeLabel(),
                'notes' => $r->notes,
                'room_number' => $r->roomUnit?->number,
                'room_name' => $r->roomUnit?->room?->name,
                'room_category' => $r->roomUnit?->room?->type,
                'guest_name' => $r->booking?->customer_name,
                'is_open' => $r->isPending(),
                'status_label' => $r->isPending() ? 'Pending' : 'Completed',
                'completed_by_name' => $r->isPending() ? null : $r->completedBy?->name,
                'created_at' => $r->created_at,
            ]);

        $maintenance = WorkOrder::with(['roomUnit.room', 'booking', 'assignedTo'])
            // Only guest-reported faults — a staff-reported one (no booking)
            // isn't a "guest service request".
            ->whereNotNull('booking_id')
            ->when($start, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->get()
            ->map(fn (WorkOrder $w) => [
                'uid' => 'wo-'.$w->id,
                'category' => 'maintenance',
                'category_label' => 'Maintenance',
                'title' => $w->title,
                'notes' => $w->description,
                'room_number' => $w->roomUnit?->number,
                'room_name' => $w->roomUnit?->room?->name,
                'room_category' => $w->roomUnit?->room?->type,
                'guest_name' => $w->booking?->customer_name,
                'is_open' => $w->isOpen(),
                'status_label' => $w->statusLabel(),
                'completed_by_name' => $w->status === WorkOrder::DONE ? $w->assignedTo?->name : null,
                'created_at' => $w->created_at,
            ]);

        return $housekeeping->concat($maintenance)
            ->when($this->category, fn ($rows) => $rows->where('category', $this->category))
            ->when($this->status === 'open', fn ($rows) => $rows->where('is_open', true))
            ->when($this->status === 'completed', fn ($rows) => $rows->where('is_open', false))
            ->when($this->search, fn ($rows) => $rows->filter(function ($r) {
                $needle = strtolower($this->search);

                return str_contains(strtolower($r['guest_name'] ?? ''), $needle)
                    || str_contains(strtolower($r['room_number'] ?? ''), $needle)
                    || str_contains(strtolower($r['room_name'] ?? ''), $needle)
                    || str_contains(strtolower($r['title']), $needle);
            }))
            ->sortByDesc('created_at')
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

    /** Download the currently filtered log as CSV. */
    public function export()
    {
        $rows = $this->allRows();
        $filename = 'service-requests-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Category', 'Title', 'Room', 'Suite', 'Category (Room)', 'Guest', 'Status', 'Completed By', 'Raised', 'Notes']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['category_label'],
                    $r['title'],
                    $r['room_number'],
                    $r['room_name'],
                    $r['room_category'],
                    $r['guest_name'],
                    $r['status_label'],
                    $r['completed_by_name'],
                    optional($r['created_at'])->format('Y-m-d H:i'),
                    $r['notes'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $rows = $this->allRows();

        $stats = [
            ['label' => 'Total Requests', 'value' => $rows->count(), 'sub' => 'Matching filters', 'accent' => '#f38c00'],
            ['label' => 'Open', 'value' => $rows->where('is_open', true)->count(), 'sub' => 'Awaiting staff', 'accent' => '#d97706'],
            ['label' => 'Housekeeping', 'value' => $rows->where('category', 'housekeeping')->count(), 'sub' => 'Guest asks', 'accent' => '#3b82f6'],
            ['label' => 'Maintenance', 'value' => $rows->where('category', 'maintenance')->count(), 'sub' => 'Guest-reported faults', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->category || $this->status || $this->from || $this->to || $this->range !== 'month');

        return view('admin.housekeeping.requests', [
            'requests' => $this->paginateRows($rows),
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'filteredCount' => $rows->count(),
        ])->layout('components.admin.app', [
            'title' => 'Service Requests',
            'subtitle' => 'Every housekeeping ask and maintenance fault raised from a guest tablet',
        ]);
    }
}
