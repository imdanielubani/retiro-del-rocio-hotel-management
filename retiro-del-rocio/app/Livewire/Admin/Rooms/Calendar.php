<?php

namespace App\Livewire\Admin\Rooms;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Calendar extends Component
{
    public Room $room;

    public string $month = '';   // Y-m

    public function mount(Room $room): void
    {
        $this->room = $room;
        $this->month = now()->format('Y-m');
    }

    public function prevMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->addMonthNoOverflow()->format('Y-m');
    }

    public function render()
    {
        $first = Carbon::parse($this->month.'-01')->startOfMonth();
        $last = $first->copy()->endOfMonth();
        $viewStart = $first->copy();                 // day 1 (inclusive)
        $viewEnd = $first->copy()->addMonthNoOverflow(); // first of next month (exclusive)
        $daysInMonth = (int) $first->daysInMonth;

        $units = $this->room->units()->get();

        // Active reservations overlapping this month for this room.
        $bookings = Booking::query()
            ->where('room_id', $this->room->id)
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereNotNull('check_in')->whereNotNull('check_out')
            ->whereDate('check_in', '<', $viewEnd->toDateString())
            ->whereDate('check_out', '>', $viewStart->toDateString())
            ->orderBy('check_in')
            ->get();

        // Allocate bookings to room numbers: honour a real check-in assignment first,
        // then first-fit the rest into a number with no overlapping reservation.
        $rows = [];
        foreach ($units as $u) {
            $rows[$u->id] = ['unit' => $u, 'ranges' => [], 'bars' => []];
        }
        $overflow = ['unit' => null, 'ranges' => [], 'bars' => []];

        $fits = function (array $ranges, Carbon $in, Carbon $out): bool {
            foreach ($ranges as [$s, $e]) {
                if ($in->lt($e) && $s->lt($out)) {
                    return false;
                }
            }

            return true;
        };

        $makeBar = function (Booking $b) use ($viewStart, $viewEnd, $daysInMonth): array {
            $spanStart = $b->check_in->greaterThan($viewStart) ? $b->check_in->copy() : $viewStart->copy();
            $spanEnd = $b->check_out->lessThan($viewEnd) ? $b->check_out->copy() : $viewEnd->copy();
            $offset = $viewStart->diffInDays($spanStart);
            $length = max(1, $spanStart->diffInDays($spanEnd));

            return [
                'booking' => $b,
                'left' => round($offset / $daysInMonth * 100, 4),
                'width' => round($length / $daysInMonth * 100, 4),
            ];
        };

        // pass 1: bookings already pinned to a number
        $pending = [];
        foreach ($bookings as $b) {
            if ($b->room_unit_id && isset($rows[$b->room_unit_id]) && $fits($rows[$b->room_unit_id]['ranges'], $b->check_in, $b->check_out)) {
                $rows[$b->room_unit_id]['ranges'][] = [$b->check_in, $b->check_out];
                $rows[$b->room_unit_id]['bars'][] = $makeBar($b);
            } else {
                $pending[] = $b;
            }
        }
        // pass 2: first-fit the rest
        foreach ($pending as $b) {
            $placed = false;
            foreach ($rows as $uid => $row) {
                if ($fits($row['ranges'], $b->check_in, $b->check_out)) {
                    $rows[$uid]['ranges'][] = [$b->check_in, $b->check_out];
                    $rows[$uid]['bars'][] = $makeBar($b);
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $overflow['bars'][] = $makeBar($b);
            }
        }

        return view('admin.rooms.calendar', [
            'title' => $first->format('F Y'),
            'days' => range(1, $daysInMonth),
            'daysInMonth' => $daysInMonth,
            'monthStart' => $first,
            'rows' => $rows,
            'overflow' => $overflow,
            'unitsCount' => $units->count(),
        ])->layout('components.admin.app', [
            'title' => 'Room Availability',
            'subtitle' => $this->room->name.' · '.$this->room->type,
        ]);
    }
}
