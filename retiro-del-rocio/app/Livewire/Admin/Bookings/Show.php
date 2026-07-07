<?php

namespace App\Livewire\Admin\Bookings;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Support\Carbon;
use Livewire\Component;

class Show extends Component
{
    public Booking $booking;

    // ----- Edit modal -----
    public bool $editing = false;

    public string $eName = '';

    public string $eEmail = '';

    public string $ePhone = '';

    public ?int $eRoomId = null;

    public string $eCheckIn = '';

    public string $eCheckOut = '';

    public int $eGuests = 1;

    public int $eAmount = 0;

    // ----- Renew / extend modal -----
    public bool $renewing = false;

    public string $renewCheckOut = '';

    // ----- Check-in assignment modal -----
    public bool $assigning = false;

    public ?int $assignUnitId = null;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load('room');
    }

    protected function refresh(): void
    {
        $this->booking->refresh()->load('room');
    }

    // Per-night rate: the linked room's price, else reconstructed from the stored total.
    protected function nightlyRate(): int
    {
        if ($this->booking->room?->price) {
            return (int) $this->booking->room->price;
        }
        $pickup = $this->booking->pickup_price ? (int) preg_replace('/[^0-9]/', '', $this->booking->pickup_price) : 0;
        $nights = max(1, (int) $this->booking->nights);
        $room = max(0, (int) round((max(0, (int) $this->booking->amount - 1250) / 1.075) - $pickup));

        return (int) round($room / $nights);
    }

    // ----- Status actions -----
    public function confirm(): void
    {
        $this->booking->update(['status' => 'paid']);
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: $this->booking->bookingCode().' confirmed.');
    }

    // Units of the booking's room that can be assigned (available + the current one).
    protected function assignableUnits()
    {
        if (! $this->booking->room_id) {
            return collect();
        }

        return RoomUnit::where('room_id', $this->booking->room_id)
            ->where(fn ($q) => $q->where('status', 'available')->orWhere('id', $this->booking->room_unit_id))
            ->orderByRaw('LENGTH(number), number')
            ->get();
    }

    public function startCheckIn(): void
    {
        $this->assignUnitId = $this->booking->room_unit_id ?? $this->assignableUnits()->first()?->id;
        $this->assigning = true;
    }

    public function confirmCheckIn(): void
    {
        // Assign the chosen room number (if any) and mark it occupied.
        if ($this->assignUnitId) {
            $unit = RoomUnit::where('room_id', $this->booking->room_id)->find($this->assignUnitId);
            if ($unit && ($unit->status === 'available' || $unit->id === $this->booking->room_unit_id)) {
                // free any previously held unit first
                if ($this->booking->room_unit_id && $this->booking->room_unit_id !== $unit->id) {
                    RoomUnit::where('id', $this->booking->room_unit_id)->update(['status' => 'available', 'booking_id' => null]);
                }
                $unit->update(['status' => 'occupied', 'booking_id' => $this->booking->id]);
                $this->booking->room_unit_id = $unit->id;
            }
        }

        $this->booking->status = 'checked_in';
        $this->booking->save();
        $this->assigning = false;
        $this->refresh();

        $suffix = $this->booking->roomUnit ? ' — assigned Room '.$this->booking->roomUnit->number : '';
        $this->dispatch('toast', type: 'success', message: $this->booking->bookingCode().' checked in'.$suffix.'.');
    }

    public function checkOut(): void
    {
        // Free the assigned room number so it's available again.
        if ($this->booking->room_unit_id) {
            RoomUnit::where('id', $this->booking->room_unit_id)->update(['status' => 'available', 'booking_id' => null]);
        }
        $this->booking->update(['status' => 'checked_out']);
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: $this->booking->bookingCode().' checked out — room is now available.');
    }

    public function cancel(): void
    {
        // Release the room number this booking held, if any.
        if ($this->booking->room_unit_id) {
            RoomUnit::where('id', $this->booking->room_unit_id)
                ->where('booking_id', $this->booking->id)
                ->update(['status' => 'available', 'booking_id' => null]);
        }
        $this->booking->status = 'cancelled';
        if (! $this->booking->refund_status) {
            $this->booking->refund_status = 'pending';
        }
        $this->booking->save();
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: $this->booking->bookingCode().' cancelled.');
    }

    // ----- Edit -----
    public function startEdit(): void
    {
        $b = $this->booking;
        $this->eName = (string) $b->customer_name;
        $this->eEmail = (string) $b->customer_email;
        $this->ePhone = (string) $b->customer_phone;
        $this->eRoomId = $b->room_id;
        $this->eCheckIn = optional($b->check_in)->toDateString() ?? '';
        $this->eCheckOut = optional($b->check_out)->toDateString() ?? '';
        $this->eGuests = (int) $b->guests;
        $this->eAmount = (int) $b->amount;
        $this->editing = true;
    }

    public function saveEdit(): void
    {
        $data = $this->validate([
            'eName' => ['required', 'string', 'max:190'],
            'eEmail' => ['nullable', 'email', 'max:190'],
            'ePhone' => ['nullable', 'string', 'max:40'],
            'eRoomId' => ['nullable', 'integer', 'exists:rooms,id'],
            'eCheckIn' => ['required', 'date'],
            'eCheckOut' => ['required', 'date', 'after:eCheckIn'],
            'eGuests' => ['required', 'integer', 'min:1', 'max:30'],
            'eAmount' => ['required', 'integer', 'min:0'],
        ]);

        $nights = max(1, Carbon::parse($data['eCheckIn'])->diffInDays(Carbon::parse($data['eCheckOut'])));
        $room = $data['eRoomId'] ? Room::find($data['eRoomId']) : null;

        $this->booking->update([
            'customer_name' => $data['eName'],
            'customer_email' => $data['eEmail'] ?: null,
            'customer_phone' => $data['ePhone'] ?: null,
            'room_id' => $room?->id,
            'room_name' => $room?->name ?? $this->booking->room_name,
            'check_in' => $data['eCheckIn'],
            'check_out' => $data['eCheckOut'],
            'nights' => $nights,
            'guests' => $data['eGuests'],
            'amount' => $data['eAmount'],
        ]);

        $this->editing = false;
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: $this->booking->bookingCode().' updated.');
    }

    // ----- Renew / extend -----
    public function startRenew(): void
    {
        $base = $this->booking->check_out ?? now();
        $this->renewCheckOut = $base->copy()->addDay()->toDateString();
        $this->renewing = true;
    }

    public function saveRenew(): void
    {
        $this->validate([
            'renewCheckOut' => ['required', 'date', 'after:'.optional($this->booking->check_out)->toDateString()],
        ], [
            'renewCheckOut.after' => 'The new check-out must be after the current check-out date.',
        ]);

        $oldOut = $this->booking->check_out;
        $newOut = Carbon::parse($this->renewCheckOut);
        $extraNights = max(1, $oldOut->diffInDays($newOut));

        $nightly = $this->nightlyRate();
        $extraRoom = $nightly * $extraNights;
        $extraVat = (int) round($extraRoom * 0.075);

        $this->booking->check_out = $newOut->toDateString();
        $this->booking->nights = (int) $this->booking->nights + $extraNights;
        $this->booking->amount = (int) $this->booking->amount + $extraRoom + $extraVat;
        // A guest who renews keeps staying — reactivate a completed stay and re-hold the room.
        if ($this->booking->status === 'checked_out') {
            $this->booking->status = 'checked_in';
            if ($this->booking->room_unit_id) {
                RoomUnit::where('id', $this->booking->room_unit_id)
                    ->where('status', 'available')
                    ->update(['status' => 'occupied', 'booking_id' => $this->booking->id]);
            }
        }
        $this->booking->save();

        $this->renewing = false;
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: 'Stay extended by '.$extraNights.' '.\Illuminate\Support\Str::plural('night', $extraNights).'. New total '.$this->booking->amountLabel().'.');
    }

    public function render()
    {
        $b = $this->booking;

        $nights = max(1, (int) $b->nights);
        $total = (int) $b->amount;
        $pickup = $b->pickup_price ? (int) preg_replace('/[^0-9]/', '', $b->pickup_price) : 0;
        $fees = $total > 1250 ? 1250 : 0;
        $subtotal = (int) round(max(0, $total - $fees) / 1.075);
        $roomRate = max(0, $subtotal - $pickup);
        $taxes = max(0, $total - $roomRate - $pickup);
        $taxPct = ($roomRate + $pickup) > 0 ? round($taxes / ($roomRate + $pickup) * 100, 1) : 0;

        $payment = [
            'room_rate' => $roomRate,
            'nights' => $nights,
            'pickup' => $pickup,
            'taxes' => $taxes,
            'tax_pct' => $taxPct,
            'service' => 0,
            'total' => $total,
        ];

        return view('admin.bookings.show', [
            'payment' => $payment,
            'nightly' => $this->nightlyRate(),
            'rooms' => Room::orderBy('name')->get(['id', 'name', 'type', 'price']),
            'assignableUnits' => $this->assigning ? $this->assignableUnits() : collect(),
        ])->layout('components.admin.app', [
            'title' => 'Booking Details',
            'subtitle' => $b->bookingCode().' · '.($b->customer_name ?: 'Guest'),
        ]);
    }
}
