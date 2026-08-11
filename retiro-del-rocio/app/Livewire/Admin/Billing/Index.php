<?php

namespace App\Livewire\Admin\Billing;

use App\Models\Booking;
use App\Support\ComputesBookingBill;
use Livewire\Component;

/**
 * Admin → Billing (Figma: admin dashboard "Billing" sidebar entry). Every
 * checked-in guest's outstanding room-charge balance — the same real numbers
 * the guest tablet's My Bills screen and the reception tablet's Bills module
 * show, computed by the shared {@see ComputesBookingBill} trait.
 */
class Index extends Component
{
    use ComputesBookingBill;

    public string $search = '';

    public bool $showDetail = false;

    public ?int $selectedBookingId = null;

    public function viewBill(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedBookingId = null;
    }

    protected function bookingsQuery()
    {
        return Booking::with('roomUnit')
            ->where('status', 'checked_in')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', "%{$this->search}%")
                ->orWhere('room_name', 'like', "%{$this->search}%")));
    }

    /** The itemised bill for the guest currently open in the detail modal, or null. */
    protected function selectedBill(): ?array
    {
        if (! $this->selectedBookingId) {
            return null;
        }

        $booking = Booking::with('roomUnit.room')->find($this->selectedBookingId);
        if (! $booking) {
            return null;
        }

        $room = $booking->roomUnit?->room ?? $booking->room;
        $breakdown = $this->billBreakdown($booking);

        return [
            'guest_name' => $booking->customer_name,
            'room_name' => $room?->name ?? $booking->room_name,
            'unit_label' => $booking->roomUnit?->number ? 'Room '.$booking->roomUnit->number : null,
            'check_in_label' => optional($booking->arrivalAt())->format('M j, Y'),
            'check_out_label' => optional($booking->departureAt())->format('M j, Y'),
            'categories' => $breakdown['categories'],
            'summary_lines' => $breakdown['summary_lines'],
            'total_due_label' => 'NGN '.number_format($breakdown['due']),
        ];
    }

    public function render()
    {
        $bookings = $this->bookingsQuery()->get();

        $rows = $bookings->map(function (Booking $booking) {
            $q = $this->billQuote($booking);

            return [
                'booking_id' => $booking->id,
                'guest_name' => $booking->customer_name,
                'room_label' => $booking->roomUnit?->number
                    ? 'Room '.$booking->roomUnit->number
                    : ($booking->room_name ?: '—'),
                'check_out_label' => optional($booking->departureAt())->format('M j, Y'),
                'total_due' => $q['due'],
                'total_due_label' => 'NGN '.number_format($q['due']),
                'has_balance' => $q['due'] > 0,
            ];
        })->sortByDesc('total_due')->values();

        $totalOutstanding = (int) $rows->sum('total_due');

        $stats = [
            ['label' => 'Total Outstanding', 'value' => 'NGN '.number_format($totalOutstanding), 'accent' => '#f38c00'],
            ['label' => 'Guests With A Balance', 'value' => (string) $rows->where('has_balance', true)->count(), 'accent' => '#dc2626'],
            ['label' => 'Checked-In Guests', 'value' => (string) $rows->count(), 'accent' => '#16a34a'],
        ];

        return view('admin.billing.index', [
            'stats' => $stats,
            'rows' => $rows,
            'selected' => $this->selectedBill(),
        ])->layout('components.admin.app', [
            'title' => 'Billing',
            'subtitle' => "Every checked-in guest's outstanding room-charge balance",
        ]);
    }
}
