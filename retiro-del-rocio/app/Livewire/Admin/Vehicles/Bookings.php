<?php

namespace App\Livewire\Admin\Vehicles;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Bookings extends Component
{
    use WithPagination;

    public string $search = '';

    // Quick date range: '' (all) | today | 7d | 30d | month | last_month
    public string $range = '';

    public bool $showFilters = false;

    public string $status = ''; // '' | confirmed | pending | cancelled

    public ?int $selectedId = null; // detail modal

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'range', 'status']);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeModal(): void
    {
        $this->selectedId = null;
    }

    public function confirm(int $id): void
    {
        $b = Booking::whereNotNull('pickup_vehicle')->find($id);
        if (! $b || $b->status !== 'pending') {
            return;
        }

        $b->update(['status' => 'paid', 'paid_at' => $b->paid_at ?? now()]);
        $this->dispatch('toast', type: 'success', message: $b->transportCode().' confirmed.');
    }

    public function reject(int $id): void
    {
        $b = Booking::whereNotNull('pickup_vehicle')->find($id);
        if (! $b || $b->status !== 'pending') {
            return;
        }

        $b->update(['status' => 'cancelled']);
        $this->dispatch('toast', type: 'success', message: $b->transportCode().' was rejected.');
        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }
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
        // Airport-pickup bookings = reservations that selected a transport vehicle.
        return Booking::query()
            ->whereNotNull('pickup_vehicle')->where('pickup_vehicle', '!=', '')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('pickup_vehicle', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
                ->orWhereRaw("CONCAT('TR-', id + 1000) like ?", ["%{$this->search}%"])))
            ->when($this->range, function ($q) {
                [$start, $end] = $this->rangeBounds();
                if ($start && $end) {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })
            ->when($this->status === 'confirmed', fn ($q) => $q->whereIn('status', ['paid', 'checked_in', 'checked_out']))
            ->when($this->status === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($this->status === 'cancelled', fn ($q) => $q->where('status', 'cancelled'));
    }

    public function render()
    {
        // Stats are computed over all pickup bookings (independent of search/range).
        $all = Booking::query()->whereNotNull('pickup_vehicle')->where('pickup_vehicle', '!=', '');

        $total = (clone $all)->count();
        $confirmed = (clone $all)->whereIn('status', ['paid', 'checked_in', 'checked_out'])->count();
        $pending = (clone $all)->where('status', 'pending')->count();
        $revenue = (int) (clone $all)->whereIn('status', ['paid', 'checked_in', 'checked_out'])
            ->get(['pickup_price'])
            ->sum(fn ($b) => $b->pickupAmount());

        $bookings = $this->baseQuery()->latest('id')->paginate(7);

        // Result summary for the toolbar (filtered set).
        $resultCount = (clone $this->baseQuery())->count();
        $resultSum = (int) (clone $this->baseQuery())->get(['pickup_price'])->sum(fn ($b) => $b->pickupAmount());

        $selected = $this->selectedId
            ? Booking::whereNotNull('pickup_vehicle')->find($this->selectedId)
            : null;

        return view('admin.vehicles.bookings', [
            'bookings' => $bookings,
            'selected' => $selected,
            'resultCount' => $resultCount,
            'resultSum' => $resultSum,
            'stats' => [
                ['label' => 'Total Bookings', 'value' => $total, 'sub' => 'All transport bookings', 'accent' => '#f38c00', 'icon' => 'calendar'],
                ['label' => 'Confirmed', 'value' => $confirmed, 'sub' => 'Scheduled & active', 'accent' => '#16a34a', 'icon' => 'check'],
                ['label' => 'Pending Review', 'value' => $pending, 'sub' => 'Awaiting confirmation', 'accent' => '#d97706', 'icon' => 'alert'],
                ['label' => 'Revenue Collected', 'value' => '₦'.number_format($revenue), 'sub' => 'Paid bookings', 'accent' => '#7c3aed', 'icon' => 'cash'],
            ],
            'hasFilters' => $this->search !== '' || $this->range !== '' || $this->status !== '',
        ])->layout('components.admin.app', [
            'title' => 'Vehicle Pickup Bookings',
            'subtitle' => 'Transport reservations from the website',
        ]);
    }
}
