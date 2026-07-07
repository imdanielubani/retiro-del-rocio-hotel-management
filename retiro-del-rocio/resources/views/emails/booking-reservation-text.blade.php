Dear {{ $booking->customer_name ?: 'Guest' }},

Thank you for booking with Retiro Del Rocio. Your payment has been received and your reservation is confirmed.

Booking reference: {{ $booking->bookingCode() }}
Room / Apartment: {{ $booking->room_name ?: '—' }}
@if ($booking->roomUnit)
Your room number: Room {{ $booking->roomUnit->number }}
@endif
Check-in: {{ optional($booking->check_in)->format('l, M j, Y') ?: '—' }} (from 2:00 PM)
Check-out: {{ optional($booking->check_out)->format('l, M j, Y') ?: '—' }} (by 12:00 PM)
Guests: {{ $booking->guests }}
@if ($booking->isPickup())
Vehicle pickup: {{ $booking->pickup_vehicle }} ({{ $booking->pickupAmountLabel() }})
@endif
Total paid: {{ $booking->amountLabel() }}
@unless ($booking->roomUnit)

Your room number will be confirmed at check-in.
@endunless

We look forward to welcoming you. If you have any questions, simply reply to this email.

Warm regards,
The Retiro Del Rocio Team

--
No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State · (+234) 7012623680
