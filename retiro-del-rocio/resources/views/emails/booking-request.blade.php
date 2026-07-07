<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation Request</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'New reservation request'])
                    <tr>
                        <td style="padding:32px;">
                            <h2 style="margin:0 0 16px;font-size:18px;">{{ $data['room'] }} — {{ $data['price'] }} / night</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;">
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;width:160px;">Number of guests</td>
                                    <td style="padding:8px 0;font-weight:bold;">{{ $data['guests'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">Check-in date</td>
                                    <td style="padding:8px 0;font-weight:bold;">{{ $data['check_in'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;">Check-out date</td>
                                    <td style="padding:8px 0;font-weight:bold;">{{ $data['check_out'] }}</td>
                                </tr>
                            </table>

                            @if (!empty($data['pickup_vehicle']))
                                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
                                    <p style="margin:0 0 12px;color:#6b7280;font-size:14px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">Vehicle Pickup</p>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;">
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;width:160px;">Vehicle</td>
                                            <td style="padding:6px 0;font-weight:bold;">{{ $data['pickup_vehicle'] }} ({{ $data['pickup_price'] }})</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Pick-up location</td>
                                            <td style="padding:6px 0;">{{ $data['location'] ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Passengers</td>
                                            <td style="padding:6px 0;">{{ $data['passengers'] ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Arrival date</td>
                                            <td style="padding:6px 0;">{{ $data['arrival_date'] ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Pick-up time</td>
                                            <td style="padding:6px 0;">{{ $data['pickup_time'] ?: '—' }}</td>
                                        </tr>
                                        @php
                                            // Airport pickups collect a flight number; the local bus services
                                            // (Valgee, Nengee, Plateau Riders) collect a bus number instead.
                                            $numberLabel = (empty($data['location']) || str_contains(strtolower((string) $data['location']), 'airport')) ? 'Flight number' : 'Bus number';
                                        @endphp
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">{{ $numberLabel }}</td>
                                            <td style="padding:6px 0;">{{ $data['flight_number'] ?: '—' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            @endif

                            @if (!empty($data['name']) || !empty($data['email']) || !empty($data['phone']))
                                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
                                    <p style="margin:0 0 12px;color:#6b7280;font-size:14px;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;">Guest contact</p>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;">
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;width:160px;">Name</td>
                                            <td style="padding:6px 0;">{{ $data['name'] ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Email</td>
                                            <td style="padding:6px 0;">{{ $data['email'] ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;color:#6b7280;">Phone</td>
                                            <td style="padding:6px 0;">{{ $data['phone'] ?: '—' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;color:#9ca3af;font-size:12px;">
                            Reservation request submitted from the Retiro Del Rocio website.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
