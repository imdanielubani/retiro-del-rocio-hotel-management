<?php

namespace App\Livewire\Admin\BarLounge;

use App\Models\DiningOrder;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    /** pending -> confirmed -> preparing -> ready -> on_way -> delivered. */
    public const FLOW = [
        'pending' => 'confirmed',
        'confirmed' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'on_way',
        'on_way' => 'delivered',
    ];

    public string $search = '';

    public string $status = '';

    public string $payment = '';

    public bool $showDetail = false;

    public ?int $selectedId = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'payment'], true)) {
            $this->resetPage();
        }
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'status', 'payment']);
        $this->resetPage();
    }

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

    public function advanceStatus(int $id): void
    {
        $order = DiningOrder::forBarLounge()->findOrFail($id);
        $next = self::FLOW[$order->status] ?? null;
        if (! $next) {
            return;
        }
        $order->update(['status' => $next]);
        $this->dispatch('toast', type: 'success', message: $order->orderCode().' marked '.$order->fresh()->statusLabel().'.');
    }

    public function setStatus(int $id, string $status): void
    {
        $order = DiningOrder::forBarLounge()->findOrFail($id);
        $order->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: $order->orderCode().' marked '.$order->fresh()->statusLabel().'.');
    }

    public function cancelOrder(int $id): void
    {
        $order = DiningOrder::forBarLounge()->findOrFail($id);
        $order->update(['status' => 'cancelled']);
        $this->dispatch('toast', type: 'success', message: $order->orderCode().' cancelled.');
    }

    protected function baseQuery()
    {
        return DiningOrder::forBarLounge()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment));
    }

    public function render()
    {
        $scope = DiningOrder::forBarLounge();
        $total = (clone $scope)->count();
        $active = (clone $scope)->whereIn('status', DiningOrder::ACTIVE_STATUSES)->count();
        $today = (clone $scope)->whereDate('created_at', now()->toDateString())->count();
        $revenue = (int) (clone $scope)->where('payment_status', 'paid')->sum('total');

        $stats = [
            ['label' => 'Total Orders', 'value' => $total, 'sub' => 'All time', 'accent' => '#f38c00'],
            ['label' => 'At the Bar', 'value' => $active, 'sub' => 'Confirmed → On the Way', 'accent' => '#2563eb'],
            ['label' => 'Today', 'value' => $today, 'sub' => 'Placed today', 'accent' => '#0891b2'],
            ['label' => 'Revenue', 'value' => '₦'.number_format($revenue), 'sub' => 'Paid orders', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->status || $this->payment);
        $filteredCount = (clone $this->baseQuery())->count();
        $orders = $this->baseQuery()->latest('id')->paginate(8);
        $selected = $this->showDetail && $this->selectedId ? DiningOrder::find($this->selectedId) : null;

        return view('admin.bar-lounge.orders', [
            'orders' => $orders,
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'filteredCount' => $filteredCount,
            'selected' => $selected,
            'flow' => self::FLOW,
        ])->layout('components.admin.app', [
            'title' => 'Bar & Lounge Orders',
            'subtitle' => 'Drink orders placed from the guest tablet\'s Place Order screen',
        ]);
    }
}
