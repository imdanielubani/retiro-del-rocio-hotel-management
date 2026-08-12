<?php

namespace App\Livewire\Admin\Kitchen;

use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Kitchen → Staff — who's currently on shift, and how many tickets each
 * individual chef has completed, so multiple accounts under the `kitchen`
 * role are tracked separately rather than as one lumped total.
 *
 * "Currently working" is presence, not a clock-in system — see
 * {@see \App\Livewire\Admin\BarLounge\StaffPerformance} for the same
 * reasoning. A ticket only counts toward a chef once it's been assigned to
 * them (the Assign to Station action) and served — an unclaimed ticket has
 * no one to credit, by design.
 */
class StaffPerformance extends Component
{
    /** today | week | month | all. */
    public string $range = 'today';

    protected function periodStart(): ?Carbon
    {
        return match ($this->range) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }

    public function render()
    {
        $from = $this->periodStart();

        // A brand-new install (or a test that hasn't seeded it) may not
        // have the `kitchen` role created yet — User::role() throws on an
        // unknown role name rather than returning an empty set.
        $staff = ! Role::where('name', 'kitchen')->exists() ? collect() : User::where('status', 'active')
            ->role('kitchen')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($from) {
                $tickets = DiningOrder::forKitchen()
                    ->where('assigned_to', $user->id)
                    ->where('status', 'delivered')
                    ->when($from, fn ($q) => $q->where('updated_at', '>=', $from))
                    ->get();

                $activeTickets = DiningOrder::forKitchen()
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready', 'on_way'])
                    ->count();

                $revenue = (int) $tickets->sum('total');
                $count = $tickets->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'online' => $user->isRecentlyActive(),
                    'active_tickets' => $activeTickets,
                    'tickets_served' => $count,
                    'revenue' => $revenue,
                    'revenue_label' => '₦'.number_format($revenue),
                ];
            })
            ->sortByDesc('tickets_served')
            ->values();

        $stats = [
            ['label' => 'On Shift Now', 'value' => $staff->where('online', true)->count(), 'sub' => 'Active in the last few minutes', 'accent' => '#16a34a'],
            ['label' => 'Kitchen Staff', 'value' => $staff->count(), 'sub' => 'Accounts holding the Kitchen role', 'accent' => '#f38c00'],
            ['label' => 'Tickets Served', 'value' => $staff->sum('tickets_served'), 'sub' => ucfirst($this->range === 'all' ? 'All time' : $this->range), 'accent' => '#2563eb'],
            ['label' => 'Total Value', 'value' => '₦'.number_format($staff->sum('revenue')), 'sub' => 'Across all kitchen staff', 'accent' => '#7c3aed'],
        ];

        return view('admin.kitchen.staff-performance', [
            'staff' => $staff,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Kitchen Staff',
            'subtitle' => 'Who\'s on shift and how many tickets each person has completed',
        ]);
    }
}
