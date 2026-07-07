<?php

namespace App\Livewire\Admin\Rooms;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $typeFilter = '';

    public function setType(string $type): void
    {
        $this->typeFilter = $this->typeFilter === $type ? '' : $type;
    }

    public function togglePublish(int $id): void
    {
        $room = Room::find($id);
        if (! $room) {
            return;
        }

        $room->is_published = ! $room->is_published;
        $room->save();

        $this->dispatch('toast', type: 'success', message: $room->name.' is now '.($room->is_published ? 'published' : 'a draft').'.');
    }

    public function delete(int $id): void
    {
        $room = Room::find($id);
        if (! $room) {
            return;
        }

        // Remove uploaded files (never the seeded /public/images assets).
        foreach (array_filter([$room->featured_image, ...($room->gallery ?? [])]) as $path) {
            if (! str_starts_with($path, 'images/') && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $name = $room->name;
        $room->delete();

        $this->dispatch('toast', type: 'success', message: $name.' was deleted.');
    }

    public function render()
    {
        // Booking-based occupancy (used as a per-card fallback for rooms with no numbers).
        $today = today();
        $occupiedIds = Booking::query()
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereNotNull('room_id')
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->pluck('room_id')->unique();

        // Occupancy is counted by physical room numbers (units) when any exist.
        $totalUnits = \App\Models\RoomUnit::count();

        if ($totalUnits > 0) {
            $occupiedUnits = \App\Models\RoomUnit::where('status', 'occupied')->count();
            $availableUnits = \App\Models\RoomUnit::where('status', 'available')->count();
            $draftUnits = \App\Models\RoomUnit::whereHas('room', fn ($q) => $q->where('is_published', false))->count();
            $occupancyPct = $totalUnits > 0 ? (int) round($occupiedUnits / $totalUnits * 100) : 0;

            $stats = [
                'total' => ['label' => 'Total Rooms', 'value' => $totalUnits, 'sub' => 'Room numbers', 'accent' => '#f38c00'],
                'occupied' => ['label' => 'Occupied', 'value' => $occupiedUnits, 'sub' => $occupancyPct.'% occupancy', 'accent' => '#16a34a'],
                'available' => ['label' => 'Available', 'value' => $availableUnits, 'sub' => 'Ready for booking', 'accent' => '#d97706'],
                'draft' => ['label' => 'Draft / Unlisted', 'value' => $draftUnits, 'sub' => 'Not visible on site', 'accent' => '#dc2626'],
            ];
        } else {
            // No room numbers added yet — fall back to room-listing counts.
            $totalRooms = Room::count();
            $publishedCount = Room::where('is_published', true)->count();
            $draftCount = Room::where('is_published', false)->count();
            $occupiedCount = Room::whereIn('id', $occupiedIds)->count();
            $occupiedPublished = Room::where('is_published', true)->whereIn('id', $occupiedIds)->count();
            $availableCount = max(0, $publishedCount - $occupiedPublished);
            $occupancyPct = $totalRooms > 0 ? (int) round($occupiedCount / $totalRooms * 100) : 0;

            $stats = [
                'total' => ['label' => 'Total Rooms', 'value' => $totalRooms, 'sub' => 'All room types', 'accent' => '#f38c00'],
                'occupied' => ['label' => 'Occupied', 'value' => $occupiedCount, 'sub' => $occupancyPct.'% occupancy', 'accent' => '#16a34a'],
                'available' => ['label' => 'Available', 'value' => $availableCount, 'sub' => 'Ready for booking', 'accent' => '#d97706'],
                'draft' => ['label' => 'Draft / Unlisted', 'value' => $draftCount, 'sub' => 'Not visible on site', 'accent' => '#dc2626'],
            ];
        }

        // Type filter chips, derived from the catalogue.
        $types = Room::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type');

        // Colour per category (legend chips, section headers, card accents).
        // Known categories use the Figma colours; anything else falls back to a palette.
        $known = [
            'rocio rooms' => '#f38c00',   // orange
            'alba' => '#3b82f6',          // blue
            'rocio loft' => '#7c3aed',    // purple
            'brisa' => '#16a34a',         // green
        ];
        $palette = ['#0891b2', '#db2777', '#ca8a04', '#dc2626', '#4f46e5'];
        $categoryColors = [];
        $fallback = 0;
        foreach ($types->values() as $type) {
            $key = strtolower((string) $type);
            $color = null;
            foreach ($known as $needle => $hex) {
                if (str_contains($key, $needle)) {
                    $color = $hex;
                    break;
                }
            }
            $categoryColors[$type] = $color ?? $palette[$fallback++ % count($palette)];
        }
        $categoryCounts = Room::query()
            ->whereNotNull('type')->where('type', '!=', '')
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        // Representative descriptor (room name) shown under each category.
        $categorySubtitles = Room::query()
            ->whereNotNull('type')->where('type', '!=', '')
            ->ordered()->get(['type', 'name'])
            ->groupBy('type')->map(fn ($g) => $g->first()->name);

        $rooms = Room::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('type', 'like', "%{$this->search}%")))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->withCount([
                'units as units_total',
                'units as units_occupied' => fn ($q) => $q->where('status', 'occupied'),
            ])
            ->ordered()
            ->get();

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'stats' => $stats,
            'types' => $types,
            'categoryColors' => $categoryColors,
            'categoryCounts' => $categoryCounts,
            'categorySubtitles' => $categorySubtitles,
            'occupiedIds' => $occupiedIds,
        ])->layout('components.admin.app', [
            'title' => 'Rooms',
            'subtitle' => 'Manage apartments shown on the website',
        ]);
    }
}
