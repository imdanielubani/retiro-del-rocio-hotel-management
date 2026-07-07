<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spa Reservation Confirmed</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Spa & Wellness reservation'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#dcfce7;color:#16a34a;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Reservation confirmed</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $booking->customer_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                Thank you for booking with {{ config('app.name') }}. Your payment has been received and your spa
                                session is confirmed. Here are your details:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:170px;border-bottom:1px solid #f1f1ee;">Reservation reference</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->sessionCode() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Service(s)</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->servicesLabel() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Date</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ optional($booking->date)->format('l, M j, Y') ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Time</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $booking->time ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Guests</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $booking->guests }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;">Total paid</td>
                                    <td style="padding:12px 16px;font-weight:bold;color:#16a34a;">{{ $booking->totalLabel() }}</td>
                                </tr>
                            </table>

                            @if ($booking->special_request)
                                <p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#6b7280;"><strong style="color:#374151;">Your note:</strong> {{ $booking->special_request }}</p>
                            @endif

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                We look forward to welcoming you for a relaxing experience. If you have any questions, simply reply to this email.
                            </p>
                            <p style="margin:16px 0 0;font-size:15px;color:#374151;">Warm regards,<br><strong>The {{ config('app.name') }} Team</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;color:#9ca3af;font-size:12px;">
                            No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State · (+234) 7012623680
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
