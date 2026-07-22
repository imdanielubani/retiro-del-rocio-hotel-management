<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1e1e1e;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;padding:24px 0;">
    <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px;max-width:92%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);">
            @include('emails.partials.header', ['subtitle' => 'Password reset'])
            <tr><td style="padding:32px;">
                <p style="margin:0 0 14px;font-size:16px;">Hi {{ $user->name }},</p>
                <p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#4b5563;">
                    We received a request to reset your Retiro Del Rocio password. Use the
                    code below to continue. It expires in {{ $ttl }} minutes.
                </p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 24px;">
                    <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:18px 34px;">
                        <div style="font-size:34px;font-weight:bold;letter-spacing:10px;color:#222a1f;">{{ $otp }}</div>
                    </td></tr>
                </table>
                <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#6b7280;">
                    Enter this code in the app to set a new password.
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                    If you didn't request this, you can safely ignore this email — your
                    password won't change.
                </p>
            </td></tr>
            <tr><td style="padding:18px 32px;background:#f9fafb;border-top:1px solid #eef0f2;">
                <p style="margin:0;font-size:12px;color:#9ca3af;">{{ config('app.name') }} · This is an automated message, please do not reply.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
