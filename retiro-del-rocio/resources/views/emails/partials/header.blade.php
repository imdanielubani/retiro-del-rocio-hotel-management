{{-- Branded email header: company logo + business name. $subtitle is optional. --}}
@php
    $logoPath = public_path('images/Logo footer.png');
    $businessName = config('app.name', 'Retiro Del Rocio');
@endphp
<tr>
    <td style="background:#222a1f;padding:22px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                @if (file_exists($logoPath))
                    <td style="padding-right:14px;vertical-align:middle;">
                        <img src="{{ $message->embed($logoPath) }}" alt="{{ $businessName }}" width="46" height="46" style="display:block;width:46px;height:46px;object-fit:contain;">
                    </td>
                @endif
                <td style="vertical-align:middle;">
                    <div style="margin:0;color:#ffffff;font-size:19px;font-weight:bold;font-family:Arial,Helvetica,sans-serif;letter-spacing:.2px;">{{ $businessName }}</div>
                    @if (! empty($subtitle))
                        <div style="margin:3px 0 0;color:#c2cdb9;font-size:13px;font-family:Arial,Helvetica,sans-serif;">{{ $subtitle }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </td>
</tr>
