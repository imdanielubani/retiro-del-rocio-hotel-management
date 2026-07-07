Dear {{ $booking->customer_name ?: 'Guest' }},

Your vehicle pickup has been arranged and confirmed. Our chauffeur will be ready to meet you on arrival.

Reference: {{ $booking->transportCode() }}
Vehicle: {{ $booking->pickup_vehicle }}
From: {{ $booking->pickupFrom() }}
To: {{ $booking->pickupTo() }}
Arrival date: {{ optional($booking->pickup_arrival_date ?: $booking->check_in)->format('l, M j, Y') ?: '—' }}
Pick-up time: {{ $booking->pickup_time ?: '—' }}
{{ $booking->pickupNumberLabel() }}: {{ $booking->pickup_flight_number ?: '—' }}
Passengers: {{ $booking->pickupPassengersLabel() }}
Pick-up fee: {{ $booking->pickupAmountLabel() }}

Please keep your phone reachable on arrival so our driver can locate you. Safe travels!

Warm regards,
The Retiro Del Rocio Team

--
No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State · (+234) 7012623680
