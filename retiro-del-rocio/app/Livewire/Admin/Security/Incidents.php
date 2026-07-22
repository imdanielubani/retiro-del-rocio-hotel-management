<?php

namespace App\Livewire\Admin\Security;

use App\Models\SosAlert;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Security → SOS Incident Register.
 *
 * The management view of every emergency raised from a guest tablet: the full
 * timeline, the responding officer and the response/resolution times. The
 * officers handle the live response on their tablets; this is the record and
 * the accountability.
 *
 * Live: it listens on the public `admin` channel (the same signal-only
 * SosAlertChanged broadcast the tablets use) and toasts on a new emergency,
 * with a `wire:poll` backstop so the register stays current even if the socket
 * is down.
 */
class Incidents extends Component
{
    use WithPagination;

    public string $search = '';

    // '', active, acknowledged, resolved, cancelled
    public string $status = '';

    // '', today, 7d, 30d, month
    public string $range = '';

    // Advanced custom range (Y-m-d).
    public string $from = '';

    public string $to = '';

    public bool $showFilters = false;

    public function updating($name): void
    {
        $this->resetPage();
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'status', 'range', 'from', 'to']);
        $this->resetPage();
    }

    /**
     * A new or changed SOS anywhere in the hotel. Livewire re-renders after this
     * runs, so the register refreshes on its own; a freshly-raised alert also
     * raises a toast so the desk notices without watching the table.
     */
    #[On('echo:admin,.sos.changed')]
    public function onSosChanged(array $payload = []): void
    {
        $id = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if ($id && $status === SosAlert::ACTIVE) {
            $alert = SosAlert::find($id);
            $room = $alert && $alert->room_number ? "Room {$alert->room_number}" : 'a guest room';
            $this->dispatch('toast', type: 'error', message: "🚨 New SOS emergency from {$room}.");
        }
    }

    /** [start, end] for the active quick-range, or [null, null] for all time. */
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

    protected function baseQuery()
    {
        return SosAlert::query()
            ->with(['acknowledgedBy:id,name', 'resolvedBy:id,name'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('room_number', 'like', "%{$this->search}%")
                ->orWhere('suite_name', 'like', "%{$this->search}%")
                ->orWhere('guest_name', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->range, function ($q) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween('raised_at', [$start, $end]);
                }
            })
            ->when($this->from, fn ($q) => $q->whereDate('raised_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('raised_at', '<=', $this->to));
    }

    /** Download the current filtered register as CSV. */
    public function export()
    {
        $rows = $this->baseQuery()->latest('raised_at')->get();
        $filename = 'sos-incidents-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Case No', 'Room', 'Suite', 'Guest', 'Status', 'Raised', 'Acknowledged', 'By', 'Response', 'Resolved', 'Resolution']);
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->caseNumber(),
                    $a->room_number,
                    $a->suite_name,
                    $a->guest_name,
                    ucfirst($a->status),
                    optional($a->raised_at)->format('Y-m-d H:i'),
                    optional($a->acknowledged_at)->format('Y-m-d H:i'),
                    optional($a->acknowledgedBy)->name,
                    $a->responseLabel(),
                    optional($a->resolved_at)->format('Y-m-d H:i'),
                    $a->resolutionLabel(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $openStatuses = SosAlert::OPEN_STATUSES;

        // Headline counters.
        $activeCount = SosAlert::where('status', SosAlert::ACTIVE)->count();
        $openCount = SosAlert::whereIn('status', $openStatuses)->count();
        $todayCount = SosAlert::whereDate('raised_at', today())->count();

        $acknowledged = SosAlert::whereNotNull('acknowledged_at')->whereNotNull('raised_at')
            ->get(['raised_at', 'acknowledged_at']);
        $avgResponse = $acknowledged->count()
            ? (int) round($acknowledged->avg(fn ($a) => $a->raised_at->diffInSeconds($a->acknowledged_at)))
            : null;

        $totalAll = SosAlert::count();
        $resolvedCount = SosAlert::where('status', SosAlert::RESOLVED)->count();

        $stats = [
            'active' => ['label' => 'Active Now', 'value' => $activeCount, 'sub' => $openCount.' open incident'.($openCount === 1 ? '' : 's'), 'accent' => '#dc2626'],
            'today' => ['label' => 'Raised Today', 'value' => $todayCount, 'sub' => now()->format('l, M j'), 'accent' => '#f38c00'],
            'response' => ['label' => 'Avg Response', 'value' => SosAlert::humanDuration($avgResponse), 'sub' => 'Raised → acknowledged', 'accent' => '#16a34a'],
            'resolved' => ['label' => 'Resolved', 'value' => $resolvedCount, 'sub' => $totalAll ? round($resolvedCount / $totalAll * 100).'% of all alerts' : 'All time', 'accent' => '#7c3aed'],
        ];

        // The live "happening now" strip: still-open incidents, newest first.
        $openIncidents = SosAlert::whereIn('status', $openStatuses)
            ->with('acknowledgedBy:id,name')
            ->latest('raised_at')
            ->get();

        $incidents = $this->baseQuery()->latest('raised_at')->paginate(10);

        return view('admin.security.incidents', [
            'incidents' => $incidents,
            'filteredCount' => (clone $this->baseQuery())->count(),
            'openIncidents' => $openIncidents,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Security',
            'subtitle' => 'SOS emergency register',
        ]);
    }
}
