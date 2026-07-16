<?php

namespace App\Livewire\Admin\Security;

use App\Models\VisitorPass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Security → Visitor Access log.
 *
 * The audit of who actually passed the gate — every verified entry (by lock or
 * keypad) and every denial — with the responding officer, the time in, the exit
 * and how long each visitor stayed. This is the read-only record of *access*,
 * distinct from the Visitor Pass register that manages the codes themselves.
 */
class VisitorAccessLog extends Component
{
    use WithPagination;

    public string $search = '';

    // '', lock, keypad, denied
    public string $method = '';

    // '', inside, exited
    public string $presence = '';

    // '', today, 7d, 30d, month
    public string $range = '';

    public string $from = '';

    public string $to = '';

    public bool $showFilters = false;

    public function updating($name): void
    {
        if ($name !== 'showFilters') {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'method', 'presence', 'range', 'from', 'to']);
        $this->resetPage();
    }

    /** Mark a visitor still inside as having left. */
    public function markExited(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->markExited()) {
            $this->dispatch('toast', type: 'error', message: 'That visitor is not currently inside.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: $pass->visitor_name.' marked as exited.');
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

    /** The moment access was decided — verified, else denied. */
    protected function accessMoment(): string
    {
        return 'COALESCE(verified_at, denied_at)';
    }

    protected function baseQuery()
    {
        // Only passes with an access decision: someone entered, or was turned away.
        return VisitorPass::query()
            ->where(fn ($q) => $q->whereNotNull('verified_at')->orWhereNotNull('denied_at'))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('visitor_name', 'like', "%{$this->search}%")
                ->orWhere('host_name', 'like', "%{$this->search}%")
                ->orWhere('room_number', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
                ->orWhere('online_code', 'like', "%{$this->search}%")))
            ->when($this->method, function ($q) {
                match ($this->method) {
                    'lock' => $q->where('verified_via', 'lock'),
                    'keypad' => $q->where('verified_via', 'keypad'),
                    'denied' => $q->where('status', VisitorPass::DENIED),
                    default => null,
                };
            })
            ->when($this->presence, function ($q) {
                match ($this->presence) {
                    'inside' => $q->where('status', VisitorPass::VERIFIED)->whereNull('exited_at'),
                    'exited' => $q->where('status', VisitorPass::VERIFIED)->whereNotNull('exited_at'),
                    default => null,
                };
            })
            ->when($this->range, function ($q) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween(DB::raw($this->accessMoment()), [$start, $end]);
                }
            })
            ->when($this->from, fn ($q) => $q->whereDate(DB::raw($this->accessMoment()), '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate(DB::raw($this->accessMoment()), '<=', $this->to));
    }

    public function export()
    {
        $rows = $this->baseQuery()->with('handledBy:id,name')->orderByDesc(DB::raw($this->accessMoment()))->get();
        $filename = 'visitor-access-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Pass No', 'Visitor', 'Host', 'Room', 'Outcome', 'Officer', 'Entered', 'Exited', 'Time inside', 'Code used']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->caseNumber(),
                    $p->visitor_name,
                    $p->host_name,
                    $p->room_number,
                    $p->accessOutcomeLabel(),
                    optional($p->handledBy)->name,
                    optional($p->verified_at)->format('Y-m-d H:i'),
                    optional($p->exited_at)->format('Y-m-d H:i'),
                    $p->verified_at ? $p->timeInsideLabel() : '—',
                    $p->online_code ?: $p->code,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $enteredToday = VisitorPass::whereDate('verified_at', today())->count();
        $inside = VisitorPass::where('status', VisitorPass::VERIFIED)->whereNull('exited_at')->count();
        $deniedToday = VisitorPass::whereDate('denied_at', today())->count();

        // Average stay for visitors who came in and left today.
        $exitedToday = VisitorPass::whereDate('verified_at', today())
            ->whereNotNull('exited_at')
            ->get(['verified_at', 'exited_at']);
        $avgSeconds = $exitedToday->count()
            ? (int) round($exitedToday->avg(fn ($p) => $p->verified_at->diffInSeconds($p->exited_at)))
            : null;

        $stats = [
            'entered' => ['label' => 'Entered Today', 'value' => $enteredToday, 'sub' => now()->format('l, M j'), 'accent' => '#16a34a'],
            'inside' => ['label' => 'Currently Inside', 'value' => $inside, 'sub' => 'On the property', 'accent' => '#f38c00'],
            'avg' => ['label' => 'Avg Time Inside', 'value' => $avgSeconds !== null ? VisitorPass::humanDurationInside($avgSeconds) : '—', 'sub' => "Today's completed visits", 'accent' => '#7c3aed'],
            'denied' => ['label' => 'Denied Today', 'value' => $deniedToday, 'sub' => 'Turned away', 'accent' => '#dc2626'],
        ];

        $entries = $this->baseQuery()
            ->with('handledBy:id,name')
            ->orderByDesc(DB::raw($this->accessMoment()))
            ->paginate(12);

        return view('admin.security.visitor-access', [
            'entries' => $entries,
            'stats' => $stats,
            'filteredCount' => (clone $this->baseQuery())->count(),
        ])->layout('components.admin.app', [
            'title' => 'Visitor Access',
            'subtitle' => 'Gate access log — entries, exits & denials',
        ]);
    }
}
