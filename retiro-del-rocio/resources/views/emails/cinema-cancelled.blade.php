<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cinema Booking Cancelled</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Cinema tickets'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#fee2e2;color:#dc2626;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Booking cancelled</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $booking->customer_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                We're writing to let you know that your cinema booking for <strong>{{ $booking->movie_title }}</strong> with {{ config('app.name') }} has been cancelled.
                                @if ($booking->payment_status === 'refunded')
                                    A refund of {{ $booking->amountLabel() }} will be processed to your original payment method within 24 hours.
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:170px;border-bottom:1px solid #f1f1ee;">Ticket ID</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Movie</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $booking->movie_title }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Date &amp; time</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ optional($booking->show_date)->format('l, M j, Y') }}@if ($booking->show_time) · {{ $booking->show_time }}@endif</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;">@if ($booking->payment_status === 'refunded')Refund amount @else Amount @endif</td>
                                    <td style="padding:12px 16px;font-weight:bold;@if ($booking->payment_status === 'refunded')color:#16a34a;@endif">{{ $booking->amountLabel() }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                If this was a mistake or you'd like to rebook, simply reply to this email and we'll be glad to help.
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
