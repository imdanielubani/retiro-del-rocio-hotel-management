<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply from Retiro Del Rocio</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
                    @include('emails.partials.header', ['subtitle' => 'A reply to your enquiry'])
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Hi {{ $contact->first_name }},</p>
                            <p style="margin:0 0 20px;white-space:pre-wrap;font-size:15px;line-height:1.7;">{{ $reply }}</p>

                            <div style="margin-top:8px;padding-top:20px;border-top:1px solid #e5e7eb;">
                                <p style="margin:0 0 6px;color:#9ca3af;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">Your original message</p>
                                <p style="margin:0;white-space:pre-wrap;font-size:14px;line-height:1.6;color:#6b7280;">{{ $contact->message ?: 'No message provided.' }}</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 32px;color:#9ca3af;font-size:12px;">
                            Retiro Del Rocio · Jos, Plateau State · {{ cms('contact.phone') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
