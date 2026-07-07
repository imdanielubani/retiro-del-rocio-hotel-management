<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gym Access Suspended</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'Gym & fitness membership'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#eef2f6;color:#475569;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Access suspended</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $membership->customer_name ?: 'Member' }},</h2>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                We're writing to let you know that access for your {{ config('app.name') }} gym membership
                                <strong>{{ $membership->code }}</strong> ({{ $membership->plan_name }}) has been temporarily suspended.
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
                                If you believe this is a mistake or you'd like to restore your access, please contact our front desk
                                and we'll be glad to help.
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
