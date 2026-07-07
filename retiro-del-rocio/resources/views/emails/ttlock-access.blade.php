<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Door Access</title>
</head>
@php
    [$accessStart, $accessEnd] = $booking->accessWindow();
    $multiGate = $booking->hasMultipleGates();
@endphp
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Access gate pass'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#ede9fe;color:#7c3aed;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">
                                {{ $updated ? 'Gate pass updated' : 'Access gate pass ready' }}
                            </div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $booking->customer_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                {{ $updated
                                    ? ($multiGate
                                        ? 'Your reservation dates changed, so we have issued you a new access gate pass. Your previous passcode no longer works. The same passcode opens every gate.'
                                        : 'Your reservation dates changed, so we have issued you a new access gate pass. Your previous passcode no longer works.')
                                    : ($multiGate
                                        ? 'Here is your access gate pass. Enter the passcode below on any gate keypad — the same code opens every gate — to enter during your stay.'
                                        : 'Here is your access gate pass. Enter the passcode below on the gate keypad to enter during your stay.') }}
                            </p>

                            {{-- Passcode (shared across all gates) --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                                <tr>
                                    <td align="center" style="background:#222a1f;border-radius:12px;padding:22px;">
                                        <div style="color:#c2cdb9;font-size:13px;letter-spacing:1px;text-transform:uppercase;">{{ $multiGate ? 'Your passcode (all gates)' : 'Your passcode' }}</div>
                                        <div style="color:#ffffff;font-size:34px;font-weight:bold;letter-spacing:6px;margin-top:6px;">{{ $booking->passcode ?: '——————' }}</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Reservation details --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:170px;border-bottom:1px solid #f1f1ee;">Reservation number</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $booking->bookingCode() }}</td>
                                </tr>
                                @if ($booking->roomUnit)
                                    <tr>
                                        <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Room number</td>
                                        <td style="padding:12px 16px;font-weight:bold;color:#f38c00;border-bottom:1px solid #f1f1ee;">Room {{ $booking->roomUnit->number }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Room / Apartment</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $booking->room_name ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Access from</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $accessStart->format('l, M j, Y · g:i A') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;">Access until</td>
                                    <td style="padding:12px 16px;">{{ $accessEnd->format('l, M j, Y · g:i A') }}</td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#9ca3af;">
                                For your security, this passcode is valid only for the dates above and expires automatically at check-out.
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
