<?php

namespace App\Livewire\Admin\Housekeeping;

use App\Models\RoomUnit;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Housekeeping → Room Status — a desk view of every room's current
 * cleanliness, mirroring what the housekeeping tablet's own Dashboard/Rooms
 * screens already show, so the front desk doesn't need a housekeeper's
 * tablet in hand to know which rooms are dirty right now.
 */
class RoomStatus extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' | clean | dirty | preparing | inspected | out_of_order

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

    /** Set a room's housekeeping status directly from the desk — the same transition the tablet's own status action performs. */
    public function setRoomStatus(int $unitId, string $status): void
    {
        $data = validator(['status' => $status], [
            'status' => ['required', Rule::in(RoomUnit::HOUSEKEEPING_STATUSES)],
        ])->validate();

        $unit = RoomUnit::find($unitId);
        if (! $unit) {
            return;
        }

        $unit->update(['housekeeping_status' => $data['status'], 'housekeeping_status_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Room '.$unit->number.' marked '.$unit->housekeepingStatusLabel().'.');
    }

    protected function baseQuery()
    {
        return RoomUnit::query()
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when(
                in_array($this->statusFilter, RoomUnit::HOUSEKEEPING_STATUSES, true),
                fn ($q) => $q->where('housekeeping_status', $this->statusFilter)
            );
    }

    public function render()
    {
        $total = RoomUnit::count();
        $counts = RoomUnit::query()
            ->selectRaw('housekeeping_status, count(*) as total')
            ->groupBy('housekeeping_status')
            ->pluck('total', 'housekeeping_status');

        $stats = [
            ['label' => 'Total Rooms', 'value' => $total, 'sub' => 'Across the property', 'accent' => '#f38c00'],
            ['label' => 'Needs Attention', 'value' => ($counts['dirty'] ?? 0) + ($counts['preparing'] ?? 0) + ($counts['out_of_order'] ?? 0), 'sub' => 'Dirty, preparing or out of order', 'accent' => '#d97706'],
            ['label' => 'Clean', 'value' => $counts['clean'] ?? 0, 'sub' => 'Ready for a guest', 'accent' => '#16a34a'],
            ['label' => 'Out of Order', 'value' => $counts['out_of_order'] ?? 0, 'sub' => 'Off the board', 'accent' => '#dc2626'],
        ];

        $rooms = $this->baseQuery()
            ->with(['room', 'booking'])
            ->orderByRaw("CASE housekeeping_status WHEN 'out_of_order' THEN 0 WHEN 'dirty' THEN 1 WHEN 'preparing' THEN 2 WHEN 'inspected' THEN 3 ELSE 4 END")
            ->orderBy('number')
            ->paginate(8);

        return view('admin.housekeeping.room-status', [
            'rooms' => $rooms,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Room Status',
            'subtitle' => 'Every room\'s current cleanliness, at a glance',
        ]);
    }
}
