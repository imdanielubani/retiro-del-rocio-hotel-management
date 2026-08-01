<?php

namespace App\Livewire\Admin\Payment;

use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\CinemaBooking;
use App\Models\GymMembership;
use App\Models\RestaurantReservation;
use App\Models\SpaBooking;
use App\Models\StayExtensionPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /**
     * A room booking counts as paid revenue once its payment lands and stays so
     * through the guest's whole stay: `paid` on arrival day, `checked_in` while
     * in-house, `checked_out` afterwards. Filtering on `paid` alone dropped every
     * guest the moment reception checked them in, zeroing the revenue cards.
     */
    private const PAID_BOOKING_STATUSES = ['paid', 'checked_in', 'checked_out'];

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

    /**
     * Constrain a room-booking query to the status the ledger should show. With
     * no explicit filter (and for 'paid') it shows real payments — a booking is
     * paid through its whole stay (paid → checked in → checked out). 'pending'
     * and 'cancelled' are only shown when the admin asks for them, so cancelled
     * and never-paid bookings don't inflate the revenue totals.
     */
    protected function applyRoomStatus($q): void
    {
        match ($this->status) {
            'pending' => $q->where('status', 'pending'),
            'cancelled' => $q->where('status', 'cancelled'),
            default => $q->whereIn('status', self::PAID_BOOKING_STATUSES),
        };
    }

    /** The same idea for the spa/gym/restaurant/cinema `payment_status` column. */
    protected function applyPaymentStatus($q): void
    {
        match ($this->status) {
            'pending' => $q->where('payment_status', 'pending'),
            'cancelled' => $q->where('payment_status', 'refunded'),
            default => $q->where('payment_status', 'paid'),
        };
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
            ->tap(fn ($q) => $this->applyRoomStatus($q))
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

        return SpaBooking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyPaymentStatus($q))
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

        return GymMembership::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('plan_name', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyPaymentStatus($q))
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

        return RestaurantReservation::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('table_label', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyPaymentStatus($q))
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

        return CinemaBooking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('movie_title', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->tap(fn ($q) => $this->applyPaymentStatus($q))
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

    // Guest-paid stay extensions, filtered. Each successful extension is its own
    // dated transaction; only 'success' rows are real payments, so pending ones
    // (the guest abandoned Paystack) never surface. A charge-to-room extension
    // is excluded too — it was never actually paid, just added to the room
    // folio, so it can't count as ledger revenue until settled. Date column =
    // paid_at.
    protected function stayExtensionQuery()
    {
        $dateCol = 'paid_at';

        return StayExtensionPayment::query()
            ->with('booking')
            ->where('status', StayExtensionPayment::SUCCESS)
            ->where('payment_method', '!=', 'room_charge')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")
                ->orWhereHas('booking', fn ($b) => $b
                    ->where('reference', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_email', 'like', "%{$this->search}%"))))
            // Only 'paid' keeps them; a pending/cancelled filter excludes all.
            ->when($this->status, fn ($q) => $this->status === 'paid' ? $q : $q->whereRaw('1 = 0'))
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

    // A guest settling their outstanding room-charge folio from My Bills —
    // this is what actually turns a "Charged to Room" spa session or stay
    // extension into real revenue. Each successful settlement is its own
    // dated transaction; only 'success' rows are real payments, so an
    // abandoned Paystack attempt never surfaces. Date column = paid_at.
    protected function billPaymentQuery()
    {
        $dateCol = 'paid_at';

        return BillPayment::query()
            ->with('booking')
            ->where('status', BillPayment::SUCCESS)
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")
                ->orWhereHas('booking', fn ($b) => $b
                    ->where('reference', 'like', "%{$this->search}%")
                    ->orWhere('customer_name', 'like', "%{$this->search}%")
                    ->orWhere('customer_email', 'like', "%{$this->search}%"))))
            // Only 'paid' keeps them; a pending/cancelled filter excludes all.
            ->when($this->status, fn ($q) => $this->status === 'paid' ? $q : $q->whereRaw('1 = 0'))
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

    /**
     * The date window the four headline cards report on — driven by the same
     * Year / Month / Day / Custom Range / quick-range filters as the
     * transaction table below, so picking a month scopes both together. With
     * nothing selected it defaults to the current calendar month.
     */
    protected function statsBounds(): array
    {
        $now = Carbon::now();

        if ($this->range) {
            [$start, $end] = $this->rangeBounds();
        } elseif ($this->from || $this->to) {
            $start = $this->from ? Carbon::parse($this->from)->startOfDay() : Carbon::createFromDate(2000, 1, 1)->startOfDay();
            $end = $this->to ? Carbon::parse($this->to)->endOfDay() : $now->copy()->endOfDay();
        } elseif ($this->year && $this->month) {
            $start = Carbon::createFromDate((int) $this->year, (int) $this->month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } elseif ($this->year) {
            $start = Carbon::createFromDate((int) $this->year, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();
        } elseif ($this->month) {
            $start = Carbon::createFromDate($now->year, (int) $this->month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();
        }

        // A Day filter narrows a single-month window down to that exact date;
        // it's ignored otherwise (e.g. "every 15th ever" isn't a real period).
        if ($this->day && $start->isSameMonth($end)) {
            $onDay = $start->copy()->day(min((int) $this->day, $start->daysInMonth));
            $start = $onDay->copy()->startOfDay();
            $end = $onDay->copy()->endOfDay();
        }

        return [$start, $end];
    }

    /** "July 2026" / "2026" / "Today" / "Jul 1 – Jul 15, 2026" — for the card headers. */
    protected function statsPeriodLabel(array $bounds): string
    {
        if (in_array($this->range, ['today', '7d', '30d'], true)) {
            return match ($this->range) {
                'today' => 'Today',
                '7d' => 'Last 7 Days',
                '30d' => 'Last 30 Days',
            };
        }

        [$start, $end] = $bounds;

        if ($start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($end->copy()->endOfYear())) {
            return $start->format('Y');
        }

        if ($start->isSameMonth($end) && $start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($end->copy()->endOfMonth())) {
            return $start->format('F Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    /**
     * Sum of a base-amount column plus its VAT — what the guest actually paid.
     * Runs on the underlying query builder (not the Eloquent model), so an
     * eager-loaded relation like `stayExtensionQuery()`'s `booking` never gets
     * in the way of a plain scalar aggregate.
     */
    protected function sumWithVat($query, string $column): int
    {
        return (int) ($query->toBase()->selectRaw("SUM({$column} + COALESCE(vat, 0)) as t")->value('t') ?? 0);
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

        // ---- The window every headline card reports on — the same Year /
        // Month / Day / Custom Range / quick-range filters used below, so
        // picking "July" scopes the cards and the transaction table together.
        [$periodStart, $periodEnd] = $this->statsBounds();
        $periodLabel = $this->statsPeriodLabel([$periodStart, $periodEnd]);

        // The equal-length window immediately before it, for the "vs previous
        // period" comparison — works for a month, a year, a custom range, etc.
        $periodSeconds = $periodStart->diffInSeconds($periodEnd) + 1;
        $prevEnd = $periodStart->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($periodSeconds - 1);

        // ---- Revenue by department (selected period) — the headline card and the breakdown ----
        $periodByDept = [
            'Rooms' => (int) Booking::whereIn('status', self::PAID_BOOKING_STATUSES)->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('amount'),
            'Spa' => (int) SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('total'),
            'Gym' => (int) GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('price'),
            'Restaurant' => (int) RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('fee'),
            'Cinema' => (int) CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('amount'),
        ];
        $periodRevenue = array_sum($periodByDept);

        // VAT (7.5%) collected in the selected period — tracked separately from revenue.
        $vatCollected = (int) Booking::whereIn('status', self::PAID_BOOKING_STATUSES)->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) StayExtensionPayment::where('status', StayExtensionPayment::SUCCESS)->where('payment_method', '!=', 'room_charge')->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat')
            + (int) BillPayment::where('status', BillPayment::SUCCESS)->whereBetween('paid_at', [$periodStart, $periodEnd])->sum('vat');

        $prevRevenue = (int) Booking::whereIn('status', self::PAID_BOOKING_STATUSES)->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('amount')
            + (int) SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('total')
            + (int) GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('amount');

        $revenueDelta = $prevRevenue > 0
            ? (int) round((($periodRevenue - $prevRevenue) / $prevRevenue) * 100)
            : ($periodRevenue > 0 ? 100 : 0);

        // A calendar month gets a friendly "from Jun" comparison; anything else
        // (a year, a custom range, a quick filter) reads as "vs previous period".
        $isCalendarMonth = $periodStart->isSameMonth($periodEnd)
            && $periodStart->isSameDay($periodStart->copy()->startOfMonth())
            && $periodEnd->isSameDay($periodEnd->copy()->endOfMonth());
        $revenueDeltaLabel = $isCalendarMonth
            ? 'from '.$prevStart->format('M')
            : 'vs previous period';

        $deptColors = ['Rooms' => '#f38c00', 'Spa' => '#7c3aed', 'Gym' => '#c2620a', 'Restaurant' => '#b91c1c', 'Cinema' => '#a16207'];
        $departments = collect($periodByDept)
            ->map(fn (int $amt, string $label) => [
                'label' => $label,
                'value' => $this->naira($amt),
                'pct' => $periodRevenue > 0 ? (int) round($amt / $periodRevenue * 100) : 0,
                'color' => $deptColors[$label],
            ])
            ->sortByDesc(fn ($d) => $d['pct'])
            ->values()
            ->all();

        // Total transactions = successful payments in the period (pending/cancelled excluded).
        $totalTransactions = Booking::whereIn('status', self::PAID_BOOKING_STATUSES)->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + SpaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + GymMembership::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + RestaurantReservation::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + CinemaBooking::where('payment_status', 'paid')->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + StayExtensionPayment::where('status', StayExtensionPayment::SUCCESS)->where('payment_method', '!=', 'room_charge')->whereBetween('paid_at', [$periodStart, $periodEnd])->count()
            + BillPayment::where('status', BillPayment::SUCCESS)->whereBetween('paid_at', [$periodStart, $periodEnd])->count();

        // Pending has no paid_at yet, so it's scoped by when it was initiated.
        $pendingAmount = (int) Booking::where('status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->sum('amount')
            + (int) SpaBooking::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->sum('total')
            + (int) GymMembership::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->sum('amount');
        $pendingCount = Booking::where('status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->count()
            + SpaBooking::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->count()
            + GymMembership::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->count()
            + RestaurantReservation::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->count()
            + CinemaBooking::where('payment_status', 'pending')->whereBetween('created_at', [$periodStart, $periodEnd])->count();

        // Refunds = money actually returned. For rooms that is the completed
        // refund_amount, not the full value of every cancelled booking (many
        // cancellations are never refunded, or only refunded in part). There's
        // no dedicated "refunded at" column, so `updated_at` is the best proxy
        // for when the refund was processed.
        $refundsAmount = (int) Booking::where('refund_status', 'completed')->whereBetween('updated_at', [$periodStart, $periodEnd])->sum('refund_amount')
            + (int) SpaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->sum('total')
            + (int) GymMembership::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->sum('price')
            + (int) RestaurantReservation::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->sum('fee')
            + (int) CinemaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->sum('amount');
        $refundsCount = Booking::where('refund_status', 'completed')->whereBetween('updated_at', [$periodStart, $periodEnd])->count()
            + SpaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->count()
            + GymMembership::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->count()
            + RestaurantReservation::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->count()
            + CinemaBooking::where('payment_status', 'refunded')->whereBetween('updated_at', [$periodStart, $periodEnd])->count();

        $stats = [
            'revenue' => [
                'label' => $periodLabel.' Revenue',
                'value' => $this->naira($periodRevenue),
                'sub' => ($revenueDelta >= 0 ? '↑ ' : '↓ ').abs($revenueDelta).'% '.$revenueDeltaLabel,
                'accent' => '#f38c00',
            ],
            'transactions' => [
                'label' => 'Total Transactions',
                'value' => number_format($totalTransactions),
                'sub' => 'Successful payments · '.$periodLabel,
                'accent' => '#16a34a',
            ],
            'pending' => [
                'label' => 'Pending Payments',
                'value' => $this->naira($pendingAmount),
                'sub' => $pendingCount.' transaction'.($pendingCount === 1 ? '' : 's').' · '.$periodLabel,
                'accent' => '#d97706',
            ],
            'refunds' => [
                'label' => 'Refunds Issued',
                'value' => $this->naira($refundsAmount),
                'sub' => $refundsCount.' · '.$periodLabel,
                'accent' => '#dc2626',
            ],
        ];

        // ---- Filtered summary (both sources) ----
        // The guest's actual total paid (base + VAT), matching the "Total Paid"
        // column in the transaction table below — not just the pre-VAT subtotal.
        $summaryCount = (clone $this->roomQuery())->count() + (clone $this->spaQuery())->count() + (clone $this->gymQuery())->count() + (clone $this->restaurantQuery())->count() + (clone $this->cinemaQuery())->count() + (clone $this->stayExtensionQuery())->count() + (clone $this->billPaymentQuery())->count();
        $summaryAmount = $this->sumWithVat($this->roomQuery(), 'amount')
            + $this->sumWithVat($this->spaQuery(), 'total')
            + $this->sumWithVat($this->gymQuery(), 'price')
            + $this->sumWithVat($this->restaurantQuery(), 'fee')
            + $this->sumWithVat($this->cinemaQuery(), 'amount')
            + $this->sumWithVat($this->stayExtensionQuery(), 'amount')
            + $this->sumWithVat($this->billPaymentQuery(), 'amount');

        // Whether any filter is active (drives the "Clear all" button).
        $hasFilters = (bool) ($this->search || $this->range || $this->year || $this->month
            || $this->day || $this->from || $this->to || $this->method || $this->status);

        // ---- Merged, manually-paginated transaction table ----
        $rows = $this->roomQuery()->get()
            ->concat($this->spaQuery()->get())
            ->concat($this->gymQuery()->get())
            ->concat($this->restaurantQuery()->get())
            ->concat($this->cinemaQuery()->get())
            ->concat($this->stayExtensionQuery()->get())
            ->concat($this->billPaymentQuery()->get())
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

        // ---- Filter option sources (union of every source) ----
        // Extract the year in PHP rather than via SQL YEAR() so the query stays
        // portable across the MySQL app DB and the SQLite test DB.
        $yearsOf = fn (string $model) => $model::query()->get(['paid_at', 'created_at'])
            ->map(fn ($r) => optional($r->paid_at ?? $r->created_at)->year);

        $years = collect()
            ->merge($yearsOf(Booking::class))
            ->merge($yearsOf(SpaBooking::class))
            ->merge($yearsOf(GymMembership::class))
            ->merge($yearsOf(RestaurantReservation::class))
            ->merge($yearsOf(CinemaBooking::class))
            ->merge($yearsOf(StayExtensionPayment::class))
            ->merge($yearsOf(BillPayment::class))
            ->filter()->unique()->sortDesc()->values();

        $methods = Booking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method')
            ->merge(SpaBooking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(GymMembership::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(RestaurantReservation::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(CinemaBooking::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(StayExtensionPayment::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->merge(BillPayment::query()->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->filter()->unique()->sort()->values();

        return view('admin.payment.index', [
            'transactions' => $transactions,
            'stats' => $stats,
            'departments' => $departments,
            'periodLabel' => $periodLabel,
            'monthRevenueLabel' => $this->naira($periodRevenue),
            'vatCollectedLabel' => $this->naira($vatCollected),
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
