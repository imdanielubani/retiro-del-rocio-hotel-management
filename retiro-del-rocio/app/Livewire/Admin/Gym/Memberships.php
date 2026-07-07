<?php

namespace App\Livewire\Admin\Gym;

use App\Mail\GymMembershipCancelled;
use App\Mail\GymMembershipConfirmation;
use App\Mail\GymMembershipSuspended;
use App\Models\GymMembership;
use App\Models\GymPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class Memberships extends Component
{
    use WithPagination;

    public string $search = '';

    public string $range = '';   // '' | today | 7d | 30d | month

    public bool $showFilters = false;

    public string $year = '';

    public string $month = '';

    public string $day = '';

    public string $from = '';

    public string $to = '';

    public string $status = '';  // active | expired | cancelled

    public string $payment = ''; // paid | pending | refunded

    public string $planFilter = '';

    // Detail
    public bool $showDetail = false;

    public ?int $selectedId = null;

    // Add membership modal
    public bool $showCreate = false;

    public string $cName = '';

    public string $cEmail = '';

    public string $cPhone = '';

    public string $cPlan = '';

    public string $cType = 'subscribe';

    public bool $cMarkPaid = true;

    protected $validationAttributes = [
        'cName' => 'name', 'cEmail' => 'email', 'cPlan' => 'plan',
    ];

    public function updating($name): void
    {
        if (in_array($name, ['search', 'range', 'year', 'month', 'day', 'from', 'to', 'status', 'payment', 'planFilter'], true)) {
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
        $this->reset(['search', 'range', 'year', 'month', 'day', 'from', 'to', 'status', 'payment', 'planFilter']);
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

    public function markActive(int $id): void
    {
        GymMembership::where('id', $id)->update(['status' => 'active']);
        $this->dispatch('toast', type: 'success', message: 'Membership marked active.');
    }

    /** Extend the membership by its plan's duration + notify the member. */
    public function renewMembership(int $id): void
    {
        $m = GymMembership::find($id);
        if (! $m) {
            return;
        }
        $base = $m->ends_at && $m->ends_at->isFuture() ? $m->ends_at->copy() : now();
        $m->update([
            'ends_at' => $base->addMonthsNoOverflow($m->durationMonths())->toDateString(),
            'status' => 'active',
            'type' => 'renewal',
        ]);
        $this->safeMail($m->customer_email, fn () => new GymMembershipConfirmation($m->fresh()));
        $this->dispatch('toast', type: 'success', message: 'Membership '.$m->code.' renewed — member notified.');
    }

    /** Suspend gym access + notify the member. */
    public function suspendAccess(int $id): void
    {
        $m = GymMembership::find($id);
        if (! $m) {
            return;
        }
        $m->update(['status' => 'suspended']);
        $this->safeMail($m->customer_email, fn () => new GymMembershipSuspended($m));
        $this->dispatch('toast', type: 'success', message: 'Access for '.$m->code.' suspended — member notified.');
    }

    public function reactivate(int $id): void
    {
        $m = GymMembership::find($id);
        if (! $m) {
            return;
        }
        $m->update(['status' => 'active']);
        $this->safeMail($m->customer_email, fn () => new GymMembershipConfirmation($m));
        $this->dispatch('toast', type: 'success', message: 'Access for '.$m->code.' reactivated — member notified.');
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

    public function markExpired(int $id): void
    {
        GymMembership::where('id', $id)->update(['status' => 'expired']);
        $this->dispatch('toast', type: 'success', message: 'Membership marked expired.');
    }

    public function recordPayment(int $id): void
    {
        $m = GymMembership::find($id);
        if (! $m) {
            return;
        }
        $m->update(['payment_status' => 'paid', 'paid_at' => $m->paid_at ?? now(), 'status' => $m->status === 'cancelled' ? 'cancelled' : 'active']);
        $this->dispatch('toast', type: 'success', message: 'Payment recorded for '.$m->code.'.');
    }

    public function cancelMembership(int $id): void
    {
        $m = GymMembership::find($id);
        if (! $m) {
            return;
        }
        $m->update([
            'status' => 'cancelled',
            'payment_status' => $m->payment_status === 'paid' ? 'refunded' : $m->payment_status,
        ]);
        $this->safeMail($m->customer_email, fn () => new GymMembershipCancelled($m->fresh()));
        $this->dispatch('toast', type: 'success', message: 'Membership '.$m->code.' cancelled — member notified.');
    }

    /* ---- Add membership ---- */

    public function openCreate(): void
    {
        $this->reset(['cName', 'cEmail', 'cPhone', 'cPlan', 'cType', 'cMarkPaid']);
        $this->cType = 'subscribe';
        $this->cMarkPaid = true;
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function createMembership(): void
    {
        $data = $this->validate([
            'cName' => ['required', 'string', 'max:160'],
            'cEmail' => ['required', 'email', 'max:190'],
            'cPhone' => ['nullable', 'string', 'max:40'],
            'cPlan' => ['required', 'exists:gym_plans,slug'],
            'cType' => ['required', 'in:subscribe,renewal'],
        ]);

        $plan = GymPlan::where('slug', $data['cPlan'])->first();

        $m = GymMembership::create([
            'code' => GymMembership::makeCode(),
            'reference' => 'GYM-ADM-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'gym_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'price' => $plan->price,
            'period' => $plan->period,
            'type' => $data['cType'],
            'customer_name' => $data['cName'],
            'customer_email' => $data['cEmail'],
            'customer_phone' => $data['cPhone'] ?: null,
            'status' => 'active',
            'payment_status' => $this->cMarkPaid ? 'paid' : 'pending',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonthsNoOverflow($plan->durationMonths())->toDateString(),
            'payment_method' => 'manual',
            'paid_at' => $this->cMarkPaid ? now() : null,
        ]);

        if ($m->customer_email) {
            try {
                Mail::to($m->customer_email)->send(new GymMembershipConfirmation($m));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'Membership '.$m->code.' created — member notified.');
    }

    protected function baseQuery()
    {
        return GymMembership::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('plan_name', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment))
            ->when($this->planFilter, fn ($q) => $q->where('gym_plan_id', $this->planFilter))
            ->when($this->year, fn ($q) => $q->whereYear('starts_at', $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth('starts_at', $this->month))
            ->when($this->day, fn ($q) => $q->whereDay('starts_at', $this->day))
            ->when($this->from, fn ($q) => $q->whereDate('starts_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('starts_at', '<=', $this->to))
            ->when($this->range, function ($q) {
                [$s, $e] = $this->rangeBounds();
                if ($s) {
                    $q->whereBetween('starts_at', [$s->toDateString(), $e->toDateString()]);
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
            default => [null, null],
        };
    }

    public function render()
    {
        $total = GymMembership::count();
        $active = GymMembership::where('status', 'active')->count();
        $expiring = GymMembership::where('status', 'active')
            ->whereNotNull('ends_at')->whereDate('ends_at', '<=', now()->addDays(7))->count();
        $revenue = (int) GymMembership::where('payment_status', 'paid')->sum('price');

        $stats = [
            ['label' => 'Total Members', 'value' => $total, 'sub' => 'All memberships', 'accent' => '#f38c00'],
            ['label' => 'Active', 'value' => $active, 'sub' => 'Currently active', 'accent' => '#16a34a'],
            ['label' => 'Expiring Soon', 'value' => $expiring, 'sub' => 'Within 7 days', 'accent' => '#d97706'],
            ['label' => 'Revenue Collected', 'value' => '₦'.number_format($revenue), 'sub' => 'From paid memberships', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->range || $this->year || $this->month
            || $this->day || $this->from || $this->to || $this->status || $this->payment || $this->planFilter);

        $filteredCount = (clone $this->baseQuery())->count();
        $memberships = $this->baseQuery()->latest('id')->paginate(8);

        $years = GymMembership::query()->selectRaw('DISTINCT YEAR(COALESCE(starts_at, created_at)) as y')
            ->orderByDesc('y')->pluck('y')->filter()->values();

        $plans = GymPlan::ordered()->get(['id', 'name', 'slug', 'price']);
        $selected = $this->showDetail && $this->selectedId ? GymMembership::find($this->selectedId) : null;

        return view('admin.gym.memberships', [
            'memberships' => $memberships,
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'filteredCount' => $filteredCount,
            'years' => $years,
            'plans' => $plans,
            'selected' => $selected,
        ])->layout('components.admin.app', [
            'title' => 'Gym Memberships',
            'subtitle' => 'Subscriptions made on the gym page',
        ]);
    }
}
