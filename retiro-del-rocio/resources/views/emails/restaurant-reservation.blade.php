<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Confirmed</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Restaurant reservation'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#dcfce7;color:#16a34a;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Reservation confirmed</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $reservation->customer_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                Thank you for reserving with {{ config('app.name') }}. Your {{ $reservation->areaLabel() }} is confirmed — we look forward to hosting you.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:180px;border-bottom:1px solid #f1f1ee;">Reservation ID</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $reservation->code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Type</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $reservation->areaLabel() }}@if ($reservation->table_label) · {{ $reservation->table_label }}@endif</td>
                                </tr>
                                @if ($reservation->occasion)
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Occasion</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $reservation->occasion }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Guests</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $reservation->guestsLabel() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Date &amp; time</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ optional($reservation->reserved_date)->format('l, M j, Y') }}@if ($reservation->reserved_time) · {{ $reservation->timeLabel() }}@endif</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;">Refundable fee paid</td>
                                    <td style="padding:12px 16px;font-weight:bold;color:#16a34a;">{{ $reservation->feeLabel() }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                Your reservation fee is fully refundable when you honour your reservation. Show your Reservation ID when you arrive. To make changes, simply reply to this email.
                            </p>
                            <p style="margin:16px 0 0;font-size:15px;color:#374151;">Bon appétit,<br><strong>The {{ config('app.name') }} Team</strong></p>
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
