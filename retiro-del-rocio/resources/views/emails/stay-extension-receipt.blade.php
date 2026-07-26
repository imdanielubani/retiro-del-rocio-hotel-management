<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stay Extension Receipt</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Stay extension receipt'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#dcfce7;color:#16a34a;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Payment confirmed</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $booking->customer_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                Your stay has been extended and your payment received. Your new checkout date is
                                <strong>{{ optional($booking->check_out)->format('l, M j, Y') ?: '—' }}</strong>. Here is your receipt:
                            </p>

                            {{-- Stay details --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:180px;border-bottom:1px solid #f1f1ee;">Receipt number</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $payment->txnId() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Booking reference</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->bookingCode() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Room / Apartment</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->room_name ?: '—' }}@if ($booking->roomUnit) · Room {{ $booking->roomUnit->number }}@endif</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Nights added</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $payment->nights }} {{ \Illuminate\Support\Str::plural('night', $payment->nights) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;">New check-out</td>
                                    <td style="padding:12px 16px;font-weight:bold;color:#f38c00;">{{ optional($booking->check_out)->format('l, M j, Y') ?: '—' }} · by 12:00 PM</td>
                                </tr>
                            </table>

                            {{-- Payment breakdown --}}
                            <p style="margin:24px 0 8px;font-size:13px;font-weight:bold;letter-spacing:.4px;text-transform:uppercase;color:#6b7280;">Payment summary</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:180px;border-bottom:1px solid #f1f1ee;">Stay extension</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f1ee;">₦{{ number_format($subtotal) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">VAT (7.5%)</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f1ee;">₦{{ number_format((int) $payment->vat) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Payment method</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f1ee;">{{ $payment->methodLabel() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Paid on</td>
                                    <td style="padding:12px 16px;text-align:right;border-bottom:1px solid #f1f1ee;">{{ optional($payment->paid_at)->format('M j, Y · g:i A') ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-weight:bold;font-size:16px;">Total paid</td>
                                    <td style="padding:14px 16px;text-align:right;font-weight:bold;font-size:16px;color:#16a34a;">₦{{ number_format((int) $payment->amount) }}</td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                Enjoy the extra time with us. If you have any questions, simply reply to this email.
                            </p>
                            <p style="margin:16px 0 0;font-size:15px;color:#374151;">Warm regards,<br><strong>The Retiro Del Rocio Team</strong></p>
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
