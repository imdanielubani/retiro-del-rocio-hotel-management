<?php

namespace App\Livewire\Admin\Housekeeping;

use App\Models\LostFoundItem;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Housekeeping → Lost & Found — every item a housekeeper has logged
 * while turning over a room, and the desk's own front door for it: search,
 * filter, hand an item back to a guest, or mark it disposed. The housekeeper
 * tablet only ever logs items and reads its own feed — this is the only
 * place staff at the desk can act on one without borrowing that tablet.
 */
class LostFound extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | unclaimed | returned | disposed

    /* ----- Return modal ----- */
    public bool $showReturn = false;

    public ?int $returnId = null;

    public string $claimantName = '';

    public string $claimantContact = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function openReturn(int $id): void
    {
        $item = LostFoundItem::find($id);
        if (! $item || ! $item->isUnclaimed()) {
            return;
        }
        $this->returnId = $item->id;
        $this->claimantName = '';
        $this->claimantContact = '';
        $this->resetValidation();
        $this->showReturn = true;
    }

    public function confirmReturn(): void
    {
        $data = $this->validate([
            'claimantName' => ['nullable', 'string', 'max:120'],
            'claimantContact' => ['nullable', 'string', 'max:120'],
        ]);

        $item = LostFoundItem::find($this->returnId);
        if (! $item) {
            return;
        }

        $item->markReturned(auth()->user(), $data['claimantName'] ?: null, $data['claimantContact'] ?: null);

        $this->showReturn = false;
        $this->dispatch('toast', type: 'success', message: $item->item_description.' marked returned.');
    }

    public function markDisposed(int $id): void
    {
        $item = LostFoundItem::find($id);
        if (! $item) {
            return;
        }

        $item->markDisposed(auth()->user());
        $this->dispatch('toast', type: 'success', message: $item->item_description.' marked disposed.');
    }

    protected function baseQuery()
    {
        return LostFoundItem::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('item_description', 'like', "%{$this->search}%")
                    ->orWhereHas('roomUnit', fn ($q) => $q->where('number', 'like', "%{$this->search}%"));
            }))
            ->when(
                in_array($this->statusFilter, LostFoundItem::STATUSES, true),
                fn ($q) => $q->where('status', $this->statusFilter)
            );
    }

    public function render()
    {
        $total = LostFoundItem::count();
        $counts = LostFoundItem::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            ['label' => 'Total Items', 'value' => $total, 'sub' => 'Ever logged', 'accent' => '#f38c00'],
            ['label' => 'Unclaimed', 'value' => $counts['unclaimed'] ?? 0, 'sub' => 'Awaiting a claim', 'accent' => '#d97706'],
            ['label' => 'Returned', 'value' => $counts['returned'] ?? 0, 'sub' => 'Handed back', 'accent' => '#16a34a'],
            ['label' => 'Disposed', 'value' => $counts['disposed'] ?? 0, 'sub' => 'Never claimed', 'accent' => '#6b7280'],
        ];

        $items = $this->baseQuery()
            ->with(['roomUnit.room', 'foundBy'])
            ->orderByRaw("CASE status WHEN 'unclaimed' THEN 0 ELSE 1 END")
            ->latest('found_at')
            ->paginate(8);

        return view('admin.housekeeping.lost-found', [
            'items' => $items,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Lost & Found',
            'subtitle' => 'Items housekeeping has logged while turning over a room',
        ]);
    }
}
