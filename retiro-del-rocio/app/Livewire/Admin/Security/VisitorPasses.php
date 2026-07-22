<?php

namespace App\Livewire\Admin\Security;

use App\Models\VisitorPass;
use App\Services\VisitorPassProvisioner;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Security → Visitor Passes register.
 *
 * The management view of every visitor a guest has invited: their online (TTLock)
 * and offline codes, whether they're inside, how they got in (lock or keypad),
 * and the TTLock provisioning state. The officers verify visitors on their
 * tablets; this is the record, the live occupancy and the recovery controls
 * (revoke, re-send, retry, mark exited).
 */
class VisitorPasses extends Component
{
    use WithPagination;

    public string $search = '';

    // '', pending, inside, exited, denied, cancelled, expired
    public string $status = '';

    // '', today, 7d, 30d, month
    public string $range = '';

    public string $from = '';

    public string $to = '';

    public bool $showFilters = false;

    /** The pass whose detail drawer is open, if any. */
    public ?int $selectedId = null;

    public function view(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

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
        $this->reset(['search', 'status', 'range', 'from', 'to']);
        $this->resetPage();
    }

    /** Revoke a still-open pass: delete the TTLock code and cancel it. */
    public function revoke(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->isOpen()) {
            $this->dispatch('toast', type: 'error', message: 'That pass can no longer be revoked.');

            return;
        }

        app(VisitorPassProvisioner::class)->revoke($pass);
        $pass->forceFill([
            'status' => VisitorPass::CANCELLED,
            'cancelled_at' => now(),
            'ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status,
        ])->save();

        $this->dispatch('toast', type: 'success', message: 'Visitor pass revoked.');
    }

    /** Turn a still-open visitor away: delete the TTLock code and deny the pass. */
    public function deny(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->isOpen()) {
            $this->dispatch('toast', type: 'error', message: 'That pass can no longer be denied.');

            return;
        }

        $pass->deny(auth()->user());
        app(VisitorPassProvisioner::class)->revoke($pass);
        $pass->forceFill(['ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status])->save();

        $this->dispatch('toast', type: 'success', message: $pass->visitor_name.' denied entry.');
    }

    /** Re-send the visitor their code(s). */
    public function resendEmail(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->visitor_email) {
            $this->dispatch('toast', type: 'error', message: 'No email on file for this visitor.');

            return;
        }

        app(VisitorPassProvisioner::class)->email($pass);
        $this->dispatch('toast', type: 'success', message: 'Pass re-sent to '.$pass->visitor_email.'.');
    }

    /** Retry TTLock provisioning for a pass that is offline/failed. */
    public function retryTtlock(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->isOpen()) {
            $this->dispatch('toast', type: 'error', message: 'This pass is no longer open.');

            return;
        }

        app(VisitorPassProvisioner::class)->provision($pass);
        $fresh = $pass->fresh();
        $ok = $fresh->ttlock_status === 'active';
        $this->dispatch('toast', type: $ok ? 'success' : 'error', message: $ok
            ? 'Online code issued for '.$fresh->visitor_name.'.'
            : 'TTLock still unavailable — the offline code stands.');
    }

    /** Mark a visitor as having left. */
    public function markExited(int $id): void
    {
        $pass = VisitorPass::find($id);
        if (! $pass || ! $pass->markExited()) {
            $this->dispatch('toast', type: 'error', message: 'That visitor is not currently inside.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: $pass->visitor_name.' marked as exited.');
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
        return VisitorPass::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('visitor_name', 'like', "%{$this->search}%")
                ->orWhere('host_name', 'like', "%{$this->search}%")
                ->orWhere('room_number', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")
                ->orWhere('online_code', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, function ($q) {
                match ($this->status) {
                    'inside' => $q->where('status', VisitorPass::VERIFIED)->whereNull('exited_at'),
                    'exited' => $q->where('status', VisitorPass::VERIFIED)->whereNotNull('exited_at'),
                    default => $q->where('status', $this->status),
                };
            })
            ->when($this->range, function ($q) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    /** Download the current filtered register as CSV. */
    public function export()
    {
        $rows = $this->baseQuery()->with('handledBy:id,name')->latest('id')->get();
        $filename = 'visitor-passes-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Pass No', 'Visitor', 'Host', 'Room', 'Status', 'Online Code', 'Offline Code', 'Entry', 'Verified by', 'Issued', 'Verified', 'Exited', 'TTLock']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->caseNumber(),
                    $p->visitor_name,
                    $p->host_name,
                    $p->room_number,
                    $p->adminStatusLabel(),
                    $p->online_code,
                    $p->code,
                    $p->entryMethodLabel(),
                    optional($p->handledBy)->name,
                    optional($p->created_at)->format('Y-m-d H:i'),
                    optional($p->verified_at)->format('Y-m-d H:i'),
                    optional($p->exited_at)->format('Y-m-d H:i'),
                    $p->ttlockLabel(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $today = VisitorPass::whereDate('created_at', today());

        $stats = [
            'today' => ['label' => 'Issued Today', 'value' => (clone $today)->count(), 'sub' => now()->format('l, M j'), 'accent' => '#f38c00'],
            'inside' => ['label' => 'Currently Inside', 'value' => VisitorPass::where('status', VisitorPass::VERIFIED)->whereNull('exited_at')->count(), 'sub' => 'Verified · not exited', 'accent' => '#16a34a'],
            'pending' => ['label' => 'Pending', 'value' => VisitorPass::where('status', VisitorPass::PENDING)->count(), 'sub' => 'Awaiting the gate', 'accent' => '#d97706'],
            'failed' => ['label' => 'TTLock Failures', 'value' => VisitorPass::where('status', VisitorPass::PENDING)->where('ttlock_status', 'failed')->count(), 'sub' => 'On offline code', 'accent' => '#dc2626'],
        ];

        $passes = $this->baseQuery()->with('handledBy:id,name')->latest('id')->paginate(10);

        $selected = $this->selectedId
            ? VisitorPass::with(['handledBy:id,name', 'booking:id,reference'])->find($this->selectedId)
            : null;

        return view('admin.security.visitor-passes', [
            'passes' => $passes,
            'stats' => $stats,
            'filteredCount' => (clone $this->baseQuery())->count(),
            'selected' => $selected,
        ])->layout('components.admin.app', [
            'title' => 'Visitor Passes',
            'subtitle' => 'Visitor access register & gate codes',
        ]);
    }
}
