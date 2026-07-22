<?php

namespace App\Livewire\Admin\Ttlock;

use App\Jobs\GenerateTtlockAccess;
use App\Jobs\RevokeTtlockAccess;
use App\Models\Booking;
use App\Services\TTLockService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * TTLock module — Access Gate Passes. Shows each guest's generated passcode +
 * QR and its status against the single Access Gate lock (no per-room mapping).
 */
class Locks extends Component
{
    use WithPagination;

    public string $search = '';

    // '' | active | failed | pending
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    /** Generate (or re-generate) the gate pass for a booking. */
    public function retryAccess(int $bookingId): void
    {
        $booking = Booking::find($bookingId);
        if (! $booking) {
            return;
        }
        if (! $booking->lockId()) {
            $this->dispatch('toast', type: 'error', message: 'Set the Access Gate lock (TTLOCK_LOCK_ID) in .env first.');

            return;
        }

        GenerateTtlockAccess::dispatchAfterResponse($booking);
        $this->dispatch('toast', type: 'success', message: 'Gate pass generation started for '.$booking->bookingCode().'.');
    }

    /** Revoke a guest's gate pass on demand. */
    public function revokeAccess(int $bookingId): void
    {
        $booking = Booking::find($bookingId);
        $grants = $booking?->revokableGrants() ?? [];
        if (! $booking || empty($grants)) {
            $this->dispatch('toast', type: 'error', message: 'No active gate pass to revoke.');

            return;
        }

        RevokeTtlockAccess::dispatchAfterResponse($booking->id, $grants);
        $this->dispatch('toast', type: 'success', message: 'Gate pass revoked for '.$booking->bookingCode().'.');
    }

    public function testConnection(): void
    {
        $result = app(TTLockService::class)->testConnection();
        $this->dispatch('toast', type: $result['ok'] ? 'success' : 'error', message: $result['message']);
    }

    /**
     * A gate pass was generated, activated, went partial, failed or was revoked
     * anywhere in the system. Livewire re-renders after this handler runs, so the
     * list, stats and status badges refresh live — no page reload. A failed pass
     * also raises a toast so the desk notices without watching the table. The
     * `wire:poll` on the view is the backstop when the socket is down.
     */
    #[On('echo:admin,.ttlock.changed')]
    public function onTtlockChanged(array $payload = []): void
    {
        if (($payload['status'] ?? null) === 'failed') {
            $id = $payload['id'] ?? null;
            $booking = $id ? Booking::find($id) : null;
            $who = $booking?->bookingCode();
            $this->dispatch('toast', type: 'error', message: '⚠️ Gate pass could not be issued'.($who ? " for {$who}" : '').'.');
        }
    }

    public function render()
    {
        $service = app(TTLockService::class);

        // Guest access records: any booking with a gate pass, plus active stays
        // still awaiting one (so they can be generated here).
        $passes = Booking::query()
            ->with('roomUnit')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
                ->orWhere('room_name', 'like', "%{$this->search}%")
                ->orWhere('passcode', 'like', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($q) => $q->where('ttlock_status', $this->statusFilter))
            ->when(! $this->statusFilter, fn ($q) => $q->where(fn ($w) => $w
                ->whereNotNull('keyboard_pwd_id')
                ->orWhereNotNull('ttlock_status')
                ->orWhereIn('status', ['paid', 'checked_in'])))
            ->latest('id')
            ->paginate(12);

        $gateIds = (new Booking)->lockIds();
        $stats = [
            'gate' => ['label' => count($gateIds) > 1 ? 'Access Gates' : 'Access Gate', 'value' => $service->isConfigured() && $gateIds ? (count($gateIds) > 1 ? count($gateIds).' gates' : 'Connected') : 'Not set', 'sub' => $gateIds ? (count($gateIds) > 1 ? 'Locks #'.implode(', #', $gateIds) : 'Lock #'.$gateIds[0]) : 'Set TTLOCK_LOCK_ID', 'accent' => '#f38c00'],
            'active' => ['label' => 'Active Passes', 'value' => Booking::whereIn('ttlock_status', ['active', 'partial'])->count(), 'sub' => 'Passcode live at the gate', 'accent' => '#16a34a'],
            'pending' => ['label' => 'Pending', 'value' => Booking::where('ttlock_status', 'pending')->count(), 'sub' => 'Being generated', 'accent' => '#d97706'],
            'failed' => ['label' => 'Failed', 'value' => Booking::where('ttlock_status', 'failed')->count(), 'sub' => 'Need attention', 'accent' => '#dc2626'],
        ];

        return view('admin.ttlock.locks', [
            'passes' => $passes,
            'stats' => $stats,
            'configured' => $service->isConfigured(),
            'gateLockId' => config('services.ttlock.lock_id'),
        ])->layout('components.admin.app', [
            'title' => 'Access Gate Passes',
            'subtitle' => 'Guest passcodes & QR codes for the access gate',
        ]);
    }
}
