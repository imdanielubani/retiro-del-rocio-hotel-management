<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — {{ $order['reference'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; background: #f4f4f5; font-family: Arial, Helvetica, sans-serif; color: #1e1e1e; }
        .sheet { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .head { background: #222a1f; padding: 28px 36px; }
        .head h1 { margin: 0; color: #f38c00; font-size: 22px; }
        .head p { margin: 4px 0 0; color: #d1dbcc; font-size: 13px; }
        .body { padding: 36px; }
        .badge { display: inline-block; background: #e8f6ec; color: #1f8a4c; font-size: 13px; font-weight: bold; padding: 6px 12px; border-radius: 999px; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        td { padding: 10px 0; vertical-align: top; }
        td.label { color: #6b7280; width: 200px; }
        td.value { font-weight: bold; text-align: right; }
        .section-title { margin: 26px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; font-weight: bold; }
        .total td { border-top: 2px solid #e5e7eb; padding-top: 16px; font-size: 20px; }
        .foot { padding: 18px 36px; background: #f9fafb; color: #9ca3af; font-size: 12px; }
        .print { text-align: center; margin: 24px auto 0; max-width: 640px; }
        .print button { background: #ba6d04; color: #fff; border: 0; border-radius: 6px; padding: 12px 28px; font-size: 15px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; } .print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="sheet">
        <div class="head">
            <h1>Retiro Del Rocio</h1>
            <p>Reservation receipt</p>
        </div>
        <div class="body">
            <span class="badge">PAID</span>
            <p style="margin:14px 0 0;color:#6b7280;font-size:13px;">Reference: <strong style="color:#1e1e1e;">{{ $order['reference'] }}</strong></p>
            @if (! empty($order['paid_at']))
                <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Paid: {{ \Illuminate\Support\Carbon::parse($order['paid_at'])->format('j M Y, g:i A') }}</p>
            @endif

            <p class="section-title">Reservation</p>
            <table>
                <tr><td class="label">Room/Apartment</td><td class="value">{{ $order['room'] }}</td></tr>
                <tr><td class="label">Guests</td><td class="value">{{ $order['guests'] }}</td></tr>
                <tr><td class="label">Check-in / Check-out</td><td class="value">{{ $order['date_range'] }}</td></tr>
                <tr><td class="label">Stay duration</td><td class="value">{{ $order['nights'] }} {{ \Illuminate\Support\Str::plural('Night', $order['nights']) }}</td></tr>
                @if ($order['pickup_vehicle'])
                    <tr><td class="label">Vehicle pickup</td><td class="value">{{ $order['pickup_vehicle'] }}</td></tr>
                @endif
            </table>

            <p class="section-title">Customer</p>
            <table>
                <tr><td class="label">Name</td><td class="value">{{ $order['customer_name'] ?: '—' }}</td></tr>
                <tr><td class="label">Phone</td><td class="value">{{ $order['customer_phone'] ?: '—' }}</td></tr>
                <tr><td class="label">Email</td><td class="value">{{ $order['customer_email'] ?: '—' }}</td></tr>
            </table>

            <p class="section-title">Payment</p>
            <table>
                <tr><td class="label">{{ $order['room'] }} ({{ number_format($order['price_per_night']) }} × {{ $order['nights'] }})</td><td class="value">{{ $order['room_subtotal_label'] }}</td></tr>
                @if ($order['pickup_price'])
                    <tr><td class="label">Vehicle pickup</td><td class="value">{{ $order['pickup_price'] }}</td></tr>
                @endif
                <tr><td class="label">VAT 7.5%</td><td class="value">{{ $order['vat_label'] }}</td></tr>
                <tr><td class="label">Fees</td><td class="value">{{ $order['fees_label'] }}</td></tr>
                <tr class="total"><td class="label" style="color:#1e1e1e;font-weight:bold;">Total paid</td><td class="value">{{ $order['total_label'] }}</td></tr>
            </table>
        </div>
        <div class="foot">Thank you for choosing Retiro Del Rocio, Jos, Plateau State.</div>
    </div>
    <div class="print"><button onclick="window.print()">Print / Save as PDF</button></div>
</body>
</html>
