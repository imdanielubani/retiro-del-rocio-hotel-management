<?php

namespace App\Support;

use App\Models\BillPayment;
use App\Models\Booking;
use App\Models\SpaBooking;
use App\Models\StayExtensionPayment;
use Illuminate\Support\Carbon;

/**
 * Shared "what does this booking currently owe, charged to the room" logic —
 * used by the guest tablet's My Bills screen, the reception tablet's Bills
 * screen and the admin Billing module, so all three compute the exact same
 * real numbers from the same sources.
 *
 * The room rate and airport pickup are settled at booking time and never
 * appear on this bill at all — they aren't a "charge to the room", they're
 * how the room was paid for in the first place. Only what's actually charged
 * to the room *during the stay* — a self-service stay extension or a spa
 * session picked "Charge to Room" — shows up here, and that's what has to be
 * zero before the guest can check out.
 */
trait ComputesBookingBill
{
    /**
     * The full priced picture for a booking's bill: the itemised charge
     * categories and what's still outstanding, net of anything already
     * pre-settled via My Bills.
     *
     * @return array{categories: array, summary_lines: array, due: int, amount_due: int, vat_due: int, paid: int}
     */
    private function billBreakdown(Booking $booking): array
    {
        // A guest never sees a Paystack payment on their bill at all — it was
        // already settled outside the room folio the moment it was paid.
        // Only an extension charged straight to the room shows as a line item
        // (and is still outstanding, since it wasn't paid at the time).
        $outstandingExtensions = $booking->stayExtensionPayments()
            ->where('status', StayExtensionPayment::SUCCESS)
            ->where('payment_method', 'room_charge')
            ->get();

        $roomLines = $outstandingExtensions->map(fn (StayExtensionPayment $extension) => [
            'label' => 'Stay Extension',
            'sub' => $extension->nights.' night(s) to '.Carbon::parse($extension->new_check_out)->format('M j, Y'),
            'amount_label' => 'NGN '.number_format((int) $extension->amount),
        ])->values()->all();

        $spaBookings = SpaBooking::where('booking_id', $booking->id)
            ->where('payment_method', 'room_charge')
            ->get();
        $spaLines = $spaBookings->map(fn (SpaBooking $b) => [
            'label' => collect($b->services)->pluck('name')->implode(', ') ?: 'Spa session',
            'sub' => $b->time.' • '.optional($b->date)->format('M j, Y'),
            'amount_label' => 'NGN '.number_format((int) $b->subtotal),
        ])->values()->all();

        // "Room Charges" totals only extensions actually charged to the room
        // — a Paystack-paid extension never shows up here, matching the same
        // rule Spa already follows.
        $outstandingExtensionBase = (int) $outstandingExtensions->sum('amount');
        $roomTotal = $outstandingExtensionBase;
        $spaSubtotal = (int) $spaBookings->sum('subtotal');
        $spaVat = (int) $spaBookings->sum('vat');

        $categories = [
            [
                'key' => 'room',
                'label' => 'Room Charges',
                'item_count' => count($roomLines),
                'amount' => $roomTotal,
                'amount_label' => $outstandingExtensions->isNotEmpty() ? 'NGN '.number_format($roomTotal) : null,
                'has_charges' => $outstandingExtensions->isNotEmpty(),
                'items' => $roomLines,
            ],
            [
                'key' => 'spa',
                'label' => 'Spa & Wellness',
                'item_count' => $spaBookings->count(),
                'amount' => $spaSubtotal,
                'amount_label' => $spaBookings->isNotEmpty() ? 'NGN '.number_format($spaSubtotal) : null,
                'has_charges' => $spaBookings->isNotEmpty(),
                'items' => $spaLines,
            ],
            [
                'key' => 'restaurant',
                'label' => 'Restaurant & Bar',
                'item_count' => 0,
                'amount' => 0,
                'amount_label' => null,
                'has_charges' => false,
                'items' => [],
            ],
            [
                'key' => 'cinema',
                'label' => 'Cinema',
                'item_count' => 0,
                'amount' => 0,
                'amount_label' => null,
                'has_charges' => false,
                'items' => [],
            ],
            [
                'key' => 'gym',
                'label' => 'Gym',
                'item_count' => 0,
                'amount' => 0,
                'amount_label' => null,
                'has_charges' => false,
                'items' => [],
            ],
        ];

        $outstandingExtensionVat = (int) $outstandingExtensions->sum('vat');
        $outstandingBase = $outstandingExtensionBase + $spaSubtotal;
        $outstandingVat = $outstandingExtensionVat + $spaVat;
        $raw = $outstandingBase + $outstandingVat;

        $paid = (int) BillPayment::where('booking_id', $booking->id)
            ->where('status', BillPayment::SUCCESS)
            ->get(['amount', 'vat'])
            ->sum(fn (BillPayment $p) => (int) $p->amount + (int) $p->vat);

        $due = max(0, $raw - $paid);
        $vatDue = min($outstandingVat, $due);
        $amountDue = $due - $vatDue;

        $summaryLines = [];
        if ($outstandingExtensionBase > 0) {
            $summaryLines[] = ['label' => 'Stay Extension', 'amount_label' => 'NGN '.number_format($outstandingExtensionBase)];
        }
        if ($spaSubtotal > 0) {
            $summaryLines[] = ['label' => 'Spa & Wellness', 'amount_label' => 'NGN '.number_format($spaSubtotal)];
        }
        if ($outstandingVat > 0) {
            $summaryLines[] = ['label' => 'VAT (7.5%)', 'amount_label' => 'NGN '.number_format($outstandingVat)];
        }
        if ($paid > 0) {
            $summaryLines[] = ['label' => 'Already Paid', 'amount_label' => '-NGN '.number_format($paid)];
        }

        return [
            'categories' => $categories,
            'summary_lines' => $summaryLines,
            'due' => $due,
            'amount_due' => $amountDue,
            'vat_due' => $vatDue,
            'paid' => $paid,
        ];
    }

    /**
     * The guest's outstanding balance only — everything charged to the room
     * during the stay, net of anything already pre-settled via My Bills. A
     * thin wrapper over {@see billBreakdown()} for callers (Pay Now, the
     * checkout gate, the Bills list) that don't need the full itemisation.
     *
     * @return array{paid: int, due: int, amount_due: int, vat_due: int}
     */
    private function billQuote(Booking $booking): array
    {
        $breakdown = $this->billBreakdown($booking);

        return [
            'paid' => $breakdown['paid'],
            'due' => $breakdown['due'],
            'amount_due' => $breakdown['amount_due'],
            'vat_due' => $breakdown['vat_due'],
        ];
    }
}
