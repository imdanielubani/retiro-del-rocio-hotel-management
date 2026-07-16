<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Visitor Pass</title>
</head>
@php
    $online = $pass->hasLiveOnlineCode() ? $pass->online_code : null;
    $offline = $pass->code;
@endphp
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Visitor pass'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#fef3c7;color:#d97706;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">
                                You're invited
                            </div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $pass->visitor_name ?: 'Guest' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                {{ $pass->host_name ?: 'A guest' }} has invited you to Retiro Del Rocio{{ $pass->room_number ? ' (Room '.$pass->room_number.')' : '' }}.
                                @if ($online)
                                    Enter the entry code below on the gate keypad to let yourself in.
                                @else
                                    Quote the entry code below to the security officer at the entrance to be verified.
                                @endif
                            </p>

                            {{-- Primary (online) entry code --}}
                            @if ($online)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
                                    <tr>
                                        <td align="center" style="background:#0f2417;border-radius:12px;padding:22px;">
                                            <div style="color:#9fd3b4;font-size:13px;letter-spacing:1px;text-transform:uppercase;">Gate entry code</div>
                                            <div style="color:#22c55e;font-size:34px;font-weight:bold;letter-spacing:6px;margin-top:6px;">{{ $online }}</div>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            {{-- Offline / manual code (fallback, or the only code when the lock is offline) --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
                                <tr>
                                    <td align="center" style="background:#222a1f;border-radius:12px;padding:{{ $online ? '16px' : '22px' }};">
                                        <div style="color:#c2cdb9;font-size:13px;letter-spacing:1px;text-transform:uppercase;">{{ $online ? 'Backup code (give to security)' : 'Your entry code' }}</div>
                                        <div style="color:#ffffff;font-size:{{ $online ? '26px' : '34px' }};font-weight:bold;letter-spacing:6px;margin-top:6px;">{{ $offline ?: '——————' }}</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Details --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;border:1px solid #eef0ec;border-radius:10px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;width:170px;border-bottom:1px solid #f1f1ee;">Pass number</td>
                                    <td style="padding:12px 16px;font-weight:bold;border-bottom:1px solid #f1f1ee;">{{ $pass->caseNumber() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Host</td>
                                    <td style="padding:12px 16px;border-bottom:1px solid #f1f1ee;">{{ $pass->host_name ?: '—' }}</td>
                                </tr>
                                @if ($pass->room_number)
                                    <tr>
                                        <td style="padding:12px 16px;color:#6b7280;border-bottom:1px solid #f1f1ee;">Room</td>
                                        <td style="padding:12px 16px;font-weight:bold;color:#f38c00;border-bottom:1px solid #f1f1ee;">Room {{ $pass->room_number }}</td>
                                    </tr>
                                @endif
                                @if ($online && $pass->expires_at)
                                    <tr>
                                        <td style="padding:12px 16px;color:#6b7280;">Valid until</td>
                                        <td style="padding:12px 16px;">{{ $pass->expires_at->format('l, M j, Y · g:i A') }}</td>
                                    </tr>
                                @endif
                            </table>

                            <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#9ca3af;">
                                For everyone's security, this code is for one-time entry and cannot be shared or reused.
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
