<?php

namespace App\Livewire\Admin\Payment;

use App\Models\Booking;
use App\Models\CinemaBooking;
use App\Models\GymMembership;
use App\Models\RestaurantReservation;
use App\Models\SpaBooking;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Quick date filter: '' | today | 7d | 30d | month | last_month
    public string $range = '';

    public bool $showFilters = false;

    // Advanced filters.
    public string $year = '';

    public string $month = '';

    public string $day = '';

    public string $from = '';

    public string $to = '';

    public string $method = '';

    public string $status = '';

    public function updating($name): void
    {
        // Any filter change resets pagination.
        $this->resetPage();
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
        $this->reset(['search', 'range', 'year', 'month', 'day', 'from', 'to', 'method', 'status']);
        $this->resetPage();
    }

    // Room bookings, filtered. Date column = paid_at.
    protected function roomQuery()
    {
        $dateCol = 'paid_at';

        return Booking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('room_name', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->method, fn ($q) => $q->where('payment_method', $this->method))
            ->when($this->year, fn ($q) => $q->whereYear($dateCol, $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->when($this->day, fn ($q) => $q->whereDay($dateCol, $this->day))
            ->when($this->from, fn ($q) => $q->whereDate($dateCol, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate($dateCol, '<=', $this->to))
            ->when($this->range, function ($q) use ($dateCol) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween($dateCol, [$start, $end]);
                }
            });
    }

    // Spa reservations, filtered. Same date column + payment-centric status map.
    protected function spaQuery()
    {
        $dateCol = 'paid_at';

        // Map the shared status filter (paid|pending|cancelled) onto the spa
        // payment_status (paid|pending|refunded) so both tables agree.
        $statusMap = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'refunded'];

        return SpaBooking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('payment_status', $statusMap[$this->status] ?? $this->status))
            ->when($this->method, fn ($q) => $q->where('payment_method', $this->method))
            ->when($this->year, fn ($q) => $q->whereYear($dateCol, $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->when($this->day, fn ($q) => $q->whereDay($dateCol, $this->day))
            ->when($this->from, fn ($q) => $q->whereDate($dateCol, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate($dateCol, '<=', $this->to))
            ->when($this->range, function ($q) use ($dateCol) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween($dateCol, [$start, $end]);
                }
            });
    }

    // Gym memberships, filtered. Same date column + payment-centric status map.
    protected function gymQuery()
    {
        $dateCol = 'paid_at';

        $statusMap = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'refunded'];

        return GymMembership::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('plan_name', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('payment_status', $statusMap[$this->status] ?? $this->status))
            ->when($this->method, fn ($q) => $q->where('payment_method', $this->method))
            ->when($this->year, fn ($q) => $q->whereYear($dateCol, $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->when($this->day, fn ($q) => $q->whereDay($dateCol, $this->day))
            ->when($this->from, fn ($q) => $q->whereDate($dateCol, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate($dateCol, '<=', $this->to))
            ->when($this->range, function ($q) use ($dateCol) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween($dateCol, [$start, $end]);
                }
            });
    }

    // Restaurant reservations, filtered. Date column = paid_at, fee = amount.
    protected function restaurantQuery()
    {
        $dateCol = 'paid_at';

        $statusMap = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'refunded'];

        return RestaurantReservation::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('table_label', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('payment_status', $statusMap[$this->status] ?? $this->status))
            ->when($this->method, fn ($q) => $q->where('payment_method', $this->method))
            ->when($this->year, fn ($q) => $q->whereYear($dateCol, $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->when($this->day, fn ($q) => $q->whereDay($dateCol, $this->day))
            ->when($this->from, fn ($q) => $q->whereDate($dateCol, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate($dateCol, '<=', $this->to))
            ->when($this->range, function ($q) use ($dateCol) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween($dateCol, [$start, $end]);
                }
            });
    }

    // Cinema bookings, filtered. Date column = paid_at, amount = amount.
    protected function cinemaQuery()
    {
        $dateCol = 'paid_at';

        $statusMap = ['paid' => 'paid', 'pending' => 'pending', 'cancelled' => 'refunded'];

        return CinemaBooking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('movie_title', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('payment_status', $statusMap[$this->status] ?? $this->status))
            ->when($this->method, fn ($q) => $q->where('payment_method', $this->method))
            ->when($this->year, fn ($q) => $q->whereYear($dateCol, $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth($dateCol, $this->month))
            ->when($this->day, fn ($q) => $q->whereDay($dateCol, $this->day))
            ->when($this->from, fn ($q) => $q->whereDate($dateCol, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate($dateCol, '<=', $this->to))
            ->when($this->range, function ($q) use ($dateCol) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween($dateCol, [$start, $end]);
                }
            });
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

    // ₦4.2M / ₦622,000 style.
    protected function naira(int $n): string
    {
        if ($n >= 1_000_000) {
            return '₦'.rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M';
        }

        return '₦'.number_format($n);
    }

    public function render()
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lastStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // ---- Stat cards (room + spa combined) ----
        $monthRevenue = (int) Booking::where('status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount')
            + (int) SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('total')
            + (int) GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');

        $lastMonthRevenue = (int) Booking::where('status', 'paid')->whereBetween('paid_at', [$lastStart, $lastEnd])->sum('amount')
            + (int) SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$lastStart, $lastEnd])->sum('total')
            + (int) GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$lastStart, $lastEnd])->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$lastStart, $lastEnd])->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$lastStart, $lastEnd])->sum('amount');

        $revenueDelta = $lastMonthRevenue > 0
            ? (int) round((($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : ($monthRevenue > 0 ? 100 : 0);

        $totalTransactions = Booking::count() + SpaBooking::count() + GymMembership::count() + RestaurantReservation::count() + CinemaBooking::count();

        $pendingAmount = (int) Booking::where('status', 'pending')->sum('amount')
            + (int) SpaBooking::where('payment_status', 'pending')->sum('total')
            + (int) GymMembership::where('payment_status', 'pending')->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'pending')->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'pending')->sum('amount');
        $pendingCount = Booking::where('status', 'pending')->count()
            + SpaBooking::where('payment_status', 'pending')->count()
            + GymMembership::where('payment_status', 'pending')->count()
            + RestaurantReservation::where('payment_status', 'pending')->count()
            + CinemaBooking::where('payment_status', 'pending')->count();

        $refundsAmount = (int) Booking::where('status', 'cancelled')->sum('amount')
            + (int) SpaBooking::where('payment_status', 'refunded')->sum('total')
            + (int) GymMembership::where('payment_status', 'refunded')->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'refunded')->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'refunded')->sum('amount');
        $refundsThisMonth = Booking::where('status', 'cancelled')->whereBetween('updated_at', [$monthStart, $monthEnd])->count()
            + SpaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$monthStart, $monthEnd])->count()
            + GymMembership::where('payment_status', 'refunded')->whereBetween('updated_at', [$monthStart, $monthEnd])->count()
            + RestaurantReservation::where('payment_status', 'refunded')->whereBetween('updated_at', [$monthStart, $monthEnd])->count()
            + CinemaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$monthStart, $monthEnd])->count();

        $stats = [
            'revenue' => [
                'label' => 'Monthly Revenue',
                'value' => $this->naira($monthRevenue),
                'sub' => ($revenueDelta >= 0 ? '↑ ' : '↓ ').abs($revenueDelta).'% from '.$now->copy()->subMonthNoOverflow()->format('M'),
                'accent' => '#f38c00',
            ],
            'transactions' => [
                'label' => 'Total Transactions',
                'value' => number_format($totalTransactions),
                'sub' => $now->format('F Y'),
                'accent' => '#16a34a',
            ],
            'pending' => [
                'label' => 'Pending Payments',
                'value' => $this->naira($pendingAmount),
                'sub' => $pendingCount.' transaction'.($pendingCount === 1 ? '' : 's'),
                'accent' => '#d97706',
            ],
            'refunds' => [
                'label' => 'Refunds Issued',
                'value' => $this->naira($refundsAmount),
                'sub' => $refundsThisMonth.' this month',
                'accent' => '#dc2626',
            ],
        ];

        // ---- Filtered summary (both sources) ----
        $summaryCount = (clone $this->roomQuery())->count() + (clone $this->spaQuery())->count() + (clone $this->gymQuery())->count() + (clone $this->restaurantQuery())->count() + (clone $this->cinemaQuery())->count();
        $summaryAmount = (int) (clone $this->roomQuery())->sum('amount') + (int) (clone $this->spaQuery())->sum('total') + (int) (clone $this->gymQuery())->sum('price') + (int) (clone $this->restaurantQuery())->sum('fee') + (int) (clone $this->cinemaQuery())->sum('amount');

        // Whether any filter is active (drives the "Clear all" button).
        $hasFilters = (bool) ($this->search || $this->range || $this->year || $this->month
            || $this->day || $this->from || $this->to || $this->method || $this->status);

        // ---- Merged, manually-paginated transaction table ----
        $rows = $this->roomQuery()->get()
            ->concat($this->spaQuery()->get())
            ->concat($this->gymQuery()->get())
            ->concat($this->restaurantQuery()->get())
            ->concat($this->cinemaQuery()->get())
            ->sortByDesc(fn ($t) => ($t->paid_at ?? $t->created_at)?->timestamp ?? 0)
            ->values();

        $perPage = 8;
        $page = (int) ($this->paginators['page'] ?? 1);
        $page = max(1, $page);

        $transactions = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        // ---- Filter option sources (union of both tables) ----
        $years = Booking::query()->selectRaw('DISTINCT YEAR(COALESCE(paid_at, created_at)) as y')->pluck('y')
            ->merge(SpaBooking::query()->selectRaw('DISTINCT YEAR(COALESCE(paid_at, created_at)) as y')->pluck('y'))
            ->merge(GymMembership::query()->selectRaw('DISTINCT YEAR(COALESCE(paid_at, created_at)) as y')->pluck('y'))
            ->merge(RestaurantReservation::query()->selectRaw('DISTINCT YEAR(COALESCE(paid_at, created_at)) as y')->pluck('y'))
            ->merge(CinemaBooking::query()->selectRaw('DISTINCT YEAR(COALESCE(paid_at, created_at)) as y')->pluck('y'))
            ->filter()->unique()->sortDesc()->values();

        $methods = Booking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method')
            ->merge(SpaBooking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(GymMembership::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(RestaurantReservation::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(CinemaBooking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->filter()->unique()->sort()->values();

        return view('admin.payment.index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'summaryCount' => $summaryCount,
            'summaryAmount' => '₦'.number_format($summaryAmount),
            'hasFilters' => $hasFilters,
            'years' => $years,
            'methods' => $methods,
        ])->layout('components.admin.app', [
            'title' => 'Payments',
            'subtitle' => 'Transactions captured from room, spa, gym, restaurant & cinema checkout',
        ]);
    }
}
