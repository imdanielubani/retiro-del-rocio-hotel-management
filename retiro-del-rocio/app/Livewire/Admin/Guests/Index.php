<?php

namespace App\Livewire\Admin\Guests;

use App\Models\Booking;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Guest Management → Guests.
 *
 * There is no Guest table: a "guest" is the set of bookings that share a
 * {@see Booking::guestKey()} (name + strongest contact). Bookings are filtered
 * first — the same search + range + advanced filters the apartment Bookings page
 * offers — then grouped in PHP and paginated manually. The 3-dot menu opens a
 * popup with that guest's lifetime stats and full stay history.
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Quick date range on check-in: '' (all time) | today | 7d | 30d | month | last_month
    public string $range = '';

    public bool $showFilters = false;

    // Advanced filters.
    public string $status = '';

    public string $roomFilter = '';

    public string $from = '';

    public string $to = '';

    /** Whether the detail popup is open. */
    public bool $showDetail = false;

    /** md5 of the selected guest's key, or null when the popup is closed. */
    public ?string $selectedId = null;

    /** Bookings that count as a real stay (not a pending request or a cancellation). */
    private const STAY_STATUSES = ['paid', 'checked_in', 'checked_out'];

    private const PER_PAGE = 8;

    /** Any filter change resets pagination (but opening the popup must not). */
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

    public function viewDetails(string $id): void
    {
        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedId = null;
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

    /** Two-letter initials for a guest's avatar disc. */
    private function initials(?string $name): string
    {
        $letters = collect(preg_split('/\s+/', trim((string) $name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)));

        return $letters->isNotEmpty() ? $letters->implode('') : 'G';
    }

    /** Bookings after the toolbar filters, before grouping. */
    protected function baseQuery()
    {
        [$start, $end] = $this->rangeBounds();

        return Booking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('customer_phone', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->roomFilter, fn ($q) => $q->where('room_name', $this->roomFilter))
            ->when($this->from, fn ($q) => $q->whereDate('check_in', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('check_in', '<=', $this->to))
            ->when($start, fn ($q) => $q->whereBetween('check_in', [$start, $end]));
    }

    /** Every guest (grouped, filtered bookings), sorted by name. */
    protected function guests(): Collection
    {
        return $this->baseQuery()
            ->orderByDesc('check_in')
            ->get()
            ->groupBy(fn (Booking $b) => $b->guestKey())
            ->map(function (Collection $group) {
                $latest = $group->first(); // newest-first from the query
                $stays = $group->whereIn('status', self::STAY_STATUSES);

                return [
                    'id' => md5($latest->guestKey()),
                    'name' => $latest->customer_name ?: 'Guest',
                    'email' => $latest->customer_email,
                    'phone' => $latest->customer_phone,
                    'initials' => $this->initials($latest->customer_name),
                    'stays' => $stays->count(),
                    'nights' => (int) $stays->sum('nights'),
                    'spend' => (int) $stays->sum('amount'),
                    'last_stay' => $latest->check_in,
                    'in_house' => $group->contains(fn (Booking $b) => $b->status === 'checked_in'),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** The selected guest's full profile + stay history (unaffected by filters). */
    protected function selectedGuest(): ?array
    {
        if (! $this->selectedId) {
            return null;
        }

        $bookings = Booking::with(['room', 'roomUnit'])
            ->orderByDesc('check_in')
            ->get()
            ->filter(fn (Booking $b) => md5($b->guestKey()) === $this->selectedId)
            ->values();

        if ($bookings->isEmpty()) {
            return null;
        }

        $latest = $bookings->first();
        $stays = $bookings->whereIn('status', self::STAY_STATUSES);
        $favouriteRoom = $stays->groupBy('room_name')->map->count()->sortDesc()->keys()->first();

        return [
            'name' => $latest->customer_name ?: 'Guest',
            'email' => $latest->customer_email,
            'phone' => $latest->customer_phone,
            'initials' => $this->initials($latest->customer_name),
            'in_house' => $bookings->contains(fn (Booking $b) => $b->status === 'checked_in'),
            'total_stays' => $stays->count(),
            'total_nights' => (int) $stays->sum('nights'),
            'total_spend_label' => '₦'.number_format((int) $stays->sum('amount')),
            'first_seen_label' => optional($bookings->last()->check_in)->format('M Y') ?: '—',
            'favourite_room' => $favouriteRoom ?: '—',
            'uses_pickup' => $bookings->contains(fn (Booking $b) => ! empty($b->pickup_vehicle)),
            'history' => $bookings,
        ];
    }

    public function render()
    {
        $all = $this->guests();

        $stats = [
            'total' => ['label' => 'Total Guests', 'value' => number_format($all->count()), 'sub' => 'Matching filters', 'accent' => '#f38c00'],
            'in_house' => ['label' => 'In-House Now', 'value' => number_format($all->where('in_house', true)->count()), 'sub' => 'Currently checked in', 'accent' => '#16a34a'],
            'repeat' => ['label' => 'Repeat Guests', 'value' => number_format($all->where('stays', '>', 1)->count()), 'sub' => 'More than one stay', 'accent' => '#7c3aed'],
            'revenue' => ['label' => 'Total Revenue', 'value' => '₦'.number_format((int) $all->sum('spend')), 'sub' => 'Across these guests', 'accent' => '#d97706'],
        ];

        $page = max(1, (int) ($this->paginators['page'] ?? 1));
        $guests = new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        $hasFilters = (bool) ($this->search || $this->range || $this->status || $this->roomFilter || $this->from || $this->to);

        return view('admin.guests.index', [
            'guests' => $guests,
            'guestCount' => $all->count(),
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'rooms' => Booking::query()->whereNotNull('room_name')->distinct()->orderBy('room_name')->pluck('room_name'),
            'selected' => $this->selectedGuest(),
        ])->layout('components.admin.app', [
            'title' => 'Guests',
            'subtitle' => 'Everyone who has stayed or booked, with their history',
        ]);
    }
}
