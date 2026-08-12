<?php

namespace App\Livewire\Admin\BarLounge;

use App\Models\BarTab;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Bar & Lounge → Staff — who's currently on shift, and how much each
 * individual bartender/waiter has sold, so multiple accounts under the
 * `bar` role are tracked separately rather than as one lumped total.
 *
 * "Currently working" is presence, not a clock-in system — it reflects the
 * same heartbeat {@see User::isRecentlyActive()} already drives the
 * chat/intercom online dot, since this app has no shift/attendance model.
 * Sales are credited to whichever staff member a settled {@see BarTab} is
 * currently assigned to — the same field the Bar Tablet itself shows as
 * "assigned_to_name" on a tab. A guest-tablet order with no bar tab has no
 * staff member to credit, so it's outside this report by design.
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
        // have the `bar` role created yet — User::role() throws on an
        // unknown role name rather than returning an empty set.
        $staff = ! Role::where('name', 'bar')->exists() ? collect() : User::where('status', 'active')
            ->role('bar')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($from) {
                $tabs = BarTab::where('status', 'settled')
                    ->where('assigned_to', $user->id)
                    ->when($from, fn ($q) => $q->where('settled_at', '>=', $from))
                    ->get();

                $openTabs = BarTab::where('status', 'open')->where('assigned_to', $user->id)->count();
                $revenue = (int) $tabs->sum('total');
                $tabCount = $tabs->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'online' => $user->isRecentlyActive(),
                    'open_tabs' => $openTabs,
                    'tabs_settled' => $tabCount,
                    'revenue' => $revenue,
                    'revenue_label' => '₦'.number_format($revenue),
                    'average_label' => $tabCount > 0 ? '₦'.number_format(intdiv($revenue, $tabCount)) : '—',
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $stats = [
            ['label' => 'On Shift Now', 'value' => $staff->where('online', true)->count(), 'sub' => 'Active in the last few minutes', 'accent' => '#16a34a'],
            ['label' => 'Bar Staff', 'value' => $staff->count(), 'sub' => 'Accounts holding the Bar role', 'accent' => '#f38c00'],
            ['label' => 'Tabs Settled', 'value' => $staff->sum('tabs_settled'), 'sub' => ucfirst($this->range === 'all' ? 'All time' : $this->range), 'accent' => '#2563eb'],
            ['label' => 'Total Revenue', 'value' => '₦'.number_format($staff->sum('revenue')), 'sub' => 'Across all bar staff', 'accent' => '#7c3aed'],
        ];

        return view('admin.bar-lounge.staff-performance', [
            'staff' => $staff,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Bar & Lounge Staff',
            'subtitle' => 'Who\'s on shift and how much each person has sold',
        ]);
    }
}
