<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you for contacting us</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'We&#39;ve received your message'])
                    <tr>
                        <td style="padding:32px;">
                            <div style="display:inline-block;background:#dcfce7;color:#16a34a;font-size:13px;font-weight:bold;padding:6px 14px;border-radius:999px;">Message received</div>
                            <h2 style="margin:18px 0 6px;font-size:20px;">Dear {{ $data['first_name'] }},</h2>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#374151;">
                                Thank you for reaching out to <strong>Retiro Del Rocio</strong>. We've received your message and a member of
                                our support team will get back to you <strong>within 30 minutes</strong> during our business hours.
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#374151;">
                                We appreciate your interest and look forward to assisting you.
                            </p>

                            @if (!empty($data['message']))
                                <div style="margin-top:8px;padding:16px 18px;background:#f9fafb;border:1px solid #eef0ec;border-radius:10px;">
                                    <p style="margin:0 0 6px;color:#9ca3af;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">Your message</p>
                                    <p style="margin:0;white-space:pre-wrap;font-size:14px;line-height:1.6;color:#374151;">{{ $data['message'] }}</p>
                                </div>
                            @endif

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;color:#374151;">
                                Need urgent assistance? Call us on <strong>(+234) 7012623680</strong>.
                            </p>
                            <p style="margin:16px 0 0;font-size:15px;color:#374151;">Warm regards,<br><strong>The Retiro Del Rocio Support Team</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;color:#9ca3af;font-size:12px;">
                            No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State · (+234) 7012623680<br>
                            This is an automated acknowledgement — there's no need to reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
