<?php

namespace App\Livewire\Admin\Restaurant;

use App\Mail\RestaurantReservationCancelled;
use App\Mail\RestaurantReservationConfirmation;
use App\Models\RestaurantReservation;
use App\Models\RestaurantTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class Reservations extends Component
{
    use WithPagination;

    public string $search = '';

    public string $range = '';   // '' | today | 7d | 30d | month

    public bool $showFilters = false;

    public string $year = '';

    public string $month = '';

    public string $from = '';

    public string $to = '';

    public string $status = '';   // confirmed | seated | completed | cancelled | no_show

    public string $payment = '';  // paid | pending | refunded

    public string $areaFilter = ''; // dining | lounge

    // Detail
    public bool $showDetail = false;

    public ?int $selectedId = null;

    // Add modal
    public bool $showCreate = false;

    public string $cName = '';

    public string $cEmail = '';

    public string $cPhone = '';

    public string $cArea = 'dining';

    public $cTableId = '';

    public string $cOccasion = 'Casual Dining';

    public $cGuests = 2;

    public string $cDate = '';

    public string $cTime = '';

    public bool $cMarkPaid = true;

    protected $validationAttributes = [
        'cName' => 'name', 'cEmail' => 'email', 'cDate' => 'date',
    ];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'range', 'year', 'month', 'from', 'to', 'status', 'payment', 'areaFilter'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $r): void
    {
        $this->range = $this->range === $r ? '' : $r;
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'range', 'year', 'month', 'from', 'to', 'status', 'payment', 'areaFilter']);
        $this->resetPage();
    }

    /* ---- Row actions ---- */

    public function viewDetails(int $id): void
    {
        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedId = null;
    }

    public function setStatus(int $id, string $status): void
    {
        RestaurantReservation::where('id', $id)->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: 'Reservation marked '.str_replace('_', ' ', $status).'.');
    }

    public function recordPayment(int $id): void
    {
        $r = RestaurantReservation::find($id);
        if (! $r) {
            return;
        }
        $r->update(['payment_status' => 'paid', 'paid_at' => $r->paid_at ?? now()]);
        $this->dispatch('toast', type: 'success', message: 'Payment recorded for '.$r->code.'.');
    }

    public function cancelReservation(int $id): void
    {
        $r = RestaurantReservation::find($id);
        if (! $r) {
            return;
        }
        $r->update([
            'status' => 'cancelled',
            'payment_status' => $r->payment_status === 'paid' ? 'refunded' : $r->payment_status,
        ]);
        $this->safeMail($r->customer_email, fn () => new RestaurantReservationCancelled($r->fresh()));
        $this->dispatch('toast', type: 'success', message: 'Reservation '.$r->code.' cancelled — guest notified, fee marked refunded.');
    }

    /** Send a mailable without letting a mail failure break the action. */
    protected function safeMail(?string $email, \Closure $make): void
    {
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send($make());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ---- Add reservation ---- */

    public function openCreate(): void
    {
        $this->reset(['cName', 'cEmail', 'cPhone', 'cArea', 'cTableId', 'cOccasion', 'cGuests', 'cDate', 'cTime', 'cMarkPaid']);
        $this->cArea = 'dining';
        $this->cOccasion = 'Casual Dining';
        $this->cGuests = 2;
        $this->cMarkPaid = true;
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function createReservation(): void
    {
        $data = $this->validate([
            'cName' => ['required', 'string', 'max:160'],
            'cEmail' => ['required', 'email', 'max:190'],
            'cPhone' => ['nullable', 'string', 'max:40'],
            'cArea' => ['required', 'in:dining,lounge'],
            'cTableId' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'cOccasion' => ['nullable', 'string', 'max:120'],
            'cGuests' => ['required', 'integer', 'min:1', 'max:30'],
            'cDate' => ['required', 'date'],
            'cTime' => ['nullable', 'string', 'max:20'],
        ]);

        $table = $data['cTableId'] ? RestaurantTable::find($data['cTableId']) : null;
        $fee = (int) preg_replace('/[^0-9]/', '', cms('restaurant.reservation_fee')) ?: 10000;

        $r = RestaurantReservation::create([
            'code' => RestaurantReservation::makeCode(),
            'reference' => 'RES-ADM-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'area' => $data['cArea'],
            'restaurant_table_id' => $table?->id,
            'table_label' => $table?->name,
            'occasion' => $data['cOccasion'] ?: null,
            'guests' => $data['cGuests'],
            'reserved_date' => $data['cDate'],
            'reserved_time' => $data['cTime'] ?: null,
            'customer_name' => $data['cName'],
            'customer_email' => $data['cEmail'],
            'customer_phone' => $data['cPhone'] ?: null,
            'status' => 'confirmed',
            'payment_status' => $this->cMarkPaid ? 'paid' : 'pending',
            'fee' => $fee,
            'payment_method' => 'manual',
            'paid_at' => $this->cMarkPaid ? now() : null,
        ]);

        if ($r->customer_email) {
            try {
                Mail::to($r->customer_email)->send(new RestaurantReservationConfirmation($r));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'Reservation '.$r->code.' created — guest notified.');
    }

    protected function baseQuery()
    {
        return RestaurantReservation::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('table_label', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment))
            ->when($this->areaFilter, fn ($q) => $q->where('area', $this->areaFilter))
            ->when($this->year, fn ($q) => $q->whereYear('reserved_date', $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth('reserved_date', $this->month))
            ->when($this->from, fn ($q) => $q->whereDate('reserved_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('reserved_date', '<=', $this->to))
            ->when($this->range, function ($q) {
                [$s, $e] = $this->rangeBounds();
                if ($s) {
                    $q->whereBetween('reserved_date', [$s->toDateString(), $e->toDateString()]);
                }
            });
    }

    protected function rangeBounds(): array
    {
        $now = Carbon::now();

        return match ($this->range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->startOfDay(), $now->copy()->addDays(6)->endOfDay()],
            '30d' => [$now->copy()->startOfDay(), $now->copy()->addDays(29)->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [null, null],
        };
    }

    public function render()
    {
        $total = RestaurantReservation::count();
        $confirmed = RestaurantReservation::whereIn('status', ['confirmed', 'seated'])->count();
        $today = RestaurantReservation::whereDate('reserved_date', now()->toDateString())
            ->whereIn('status', ['confirmed', 'seated'])->count();
        $revenue = (int) RestaurantReservation::where('payment_status', 'paid')->sum('fee');

        $stats = [
            ['label' => 'Total Reservations', 'value' => $total, 'sub' => 'All time', 'accent' => '#f38c00'],
            ['label' => 'Upcoming', 'value' => $confirmed, 'sub' => 'Confirmed / seated', 'accent' => '#16a34a'],
            ['label' => 'Today', 'value' => $today, 'sub' => 'Arriving today', 'accent' => '#0891b2'],
            ['label' => 'Fees Collected', 'value' => '₦'.number_format($revenue), 'sub' => 'Refundable deposits', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->range || $this->year || $this->month
            || $this->from || $this->to || $this->status || $this->payment || $this->areaFilter);

        $filteredCount = (clone $this->baseQuery())->count();
        $reservations = $this->baseQuery()->latest('id')->paginate(8);

        $years = RestaurantReservation::query()->selectRaw('DISTINCT YEAR(COALESCE(reserved_date, created_at)) as y')
            ->orderByDesc('y')->pluck('y')->filter()->values();

        $tables = RestaurantTable::active()->ordered()->get(['id', 'name', 'area', 'capacity']);
        $selected = $this->showDetail && $this->selectedId ? RestaurantReservation::find($this->selectedId) : null;

        return view('admin.restaurant.reservations', [
            'reservations' => $reservations,
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'filteredCount' => $filteredCount,
            'years' => $years,
            'tables' => $tables,
            'selected' => $selected,
        ])->layout('components.admin.app', [
            'title' => 'Restaurant Reservations',
            'subtitle' => 'Table & lounge reservations made on the restaurant page',
        ]);
    }
}
