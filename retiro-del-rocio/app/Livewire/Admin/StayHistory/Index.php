<?php

namespace App\Livewire\Admin\StayHistory;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Guest Management → Stay History.
 *
 * The chronological record of every real stay — bookings that were paid, are
 * in-house, or have checked out (pending requests and cancellations are not
 * stays). Read-only, and filterable with the same search + range + advanced
 * filters the apartment Bookings page offers.
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Quick date range on check-in: '' (all time) | today | 7d | 30d | month | last_month
    public string $range = '';

    public bool $showFilters = false;

    // Advanced filters. Status: '' | paid (upcoming) | checked_in (in-house) | checked_out (completed)
    public string $status = '';

    public string $roomFilter = '';

    public string $from = '';

    public string $to = '';

    /** Bookings that count as a real stay. */
    private const STAY_STATUSES = ['paid', 'checked_in', 'checked_out'];

    /** Any filter change resets pagination. */
    public function updating($name): void
    {
        if (in_array($name, ['search', 'range', 'status', 'roomFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'range', 'status', 'roomFilter', 'from', 'to']);
        $this->resetPage();
    }

    protected function rangeBounds(): array
    {
        $now = Carbon::now();

        return match ($this->range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [null, null],
        };
    }

    protected function baseQuery()
    {
        [$start, $end] = $this->rangeBounds();

        return Booking::query()
            ->with(['room', 'roomUnit'])
            ->whereIn('status', self::STAY_STATUSES)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('room_name', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->roomFilter, fn ($q) => $q->where('room_name', $this->roomFilter))
            ->when($this->from, fn ($q) => $q->whereDate('check_in', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('check_in', '<=', $this->to))
            ->when($start, fn ($q) => $q->whereBetween('check_in', [$start, $end]));
    }

    public function render()
    {
        $totalStays = (clone $this->baseQuery())->count();
        $totalNights = (int) (clone $this->baseQuery())->sum('nights');
        $totalRevenue = (int) (clone $this->baseQuery())->sum('amount');
        $avgStay = $totalStays > 0 ? round($totalNights / $totalStays, 1) : 0;

        $stats = [
            'stays' => ['label' => 'Total Stays', 'value' => number_format($totalStays), 'sub' => 'Paid, in-house & completed', 'accent' => '#f38c00'],
            'nights' => ['label' => 'Total Nights', 'value' => number_format($totalNights), 'sub' => 'Room-nights sold', 'accent' => '#16a34a'],
            'revenue' => ['label' => 'Total Revenue', 'value' => '₦'.number_format($totalRevenue), 'sub' => 'From these stays', 'accent' => '#d97706'],
            'avg' => ['label' => 'Avg Stay Length', 'value' => $avgStay.' '.($avgStay == 1 ? 'night' : 'nights'), 'sub' => 'Per stay', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->range || $this->status || $this->roomFilter || $this->from || $this->to);

        return view('admin.stay-history.index', [
            'stays' => $this->baseQuery()->orderByDesc('check_in')->paginate(10),
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'rooms' => Booking::query()->whereNotNull('room_name')->distinct()->orderBy('room_name')->pluck('room_name'),
        ])->layout('components.admin.app', [
            'title' => 'Stay History',
            'subtitle' => 'The record of every guest stay',
        ]);
    }
}
