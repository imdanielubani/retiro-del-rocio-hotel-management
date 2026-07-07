<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $r->code }} — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f5; font-family: Arial, Helvetica, sans-serif; color: #1e1e1e; padding: 24px; }
        .sheet { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,.08); }
        .head { background: #222a1f; color: #fff; padding: 26px 32px; display: flex; align-items: center; justify-content: space-between; }
        .head h1 { margin: 0; font-size: 20px; }
        .head .badge { background: #dcfce7; color: #16a34a; font-size: 12px; font-weight: bold; padding: 6px 12px; border-radius: 999px; }
        .body { padding: 28px 32px; }
        .code { font-size: 26px; font-weight: 800; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 15px; }
        td { padding: 11px 0; border-bottom: 1px solid #f1f1ee; }
        td.k { color: #6b7280; width: 50%; }
        td.v { text-align: right; font-weight: 600; }
        .total td { border-bottom: 0; padding-top: 16px; font-size: 18px; }
        .total .v { color: #16a34a; }
        .foot { padding: 16px 32px; background: #f9fafb; color: #9ca3af; font-size: 12px; }
        .print { display: inline-block; margin: 18px auto 0; background: #f38c00; color: #fff; text-decoration: none; padding: 11px 22px; border-radius: 10px; font-weight: 700; border: 0; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; } .noprint { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="sheet">
        <div class="head">
            <h1>{{ config('app.name') }}</h1>
            <span class="badge">{{ $r->paymentLabel() }}</span>
        </div>
        <div class="body">
            <p style="margin:0 0 4px;color:#6b7280;font-size:13px;text-transform:uppercase;letter-spacing:.5px;">Reservation receipt</p>
            <div class="code">{{ $r->code }}</div>
            <table>
                <tr><td class="k">Guest</td><td class="v">{{ $r->customer_name ?: '—' }}</td></tr>
                <tr><td class="k">Email</td><td class="v">{{ $r->customer_email ?: '—' }}</td></tr>
                @if ($r->customer_phone)<tr><td class="k">Phone</td><td class="v">{{ $r->customer_phone }}</td></tr>@endif
                <tr><td class="k">Type</td><td class="v">{{ $r->areaLabel() }}@if ($r->table_label) · {{ $r->table_label }}@endif</td></tr>
                <tr><td class="k">Occasion</td><td class="v">{{ $r->occasion ?: '—' }}</td></tr>
                <tr><td class="k">Party size</td><td class="v">{{ $r->guestsLabel() }}</td></tr>
                <tr><td class="k">Date</td><td class="v">{{ optional($r->reserved_date)->format('M j, Y') ?: '—' }}</td></tr>
                <tr><td class="k">Time</td><td class="v">{{ $r->timeLabel() }}</td></tr>
                <tr><td class="k">Payment method</td><td class="v">{{ $r->methodLabel() }}</td></tr>
                @if ($r->reference)<tr><td class="k">Reference</td><td class="v">{{ $r->reference }}</td></tr>@endif
                <tr class="total"><td class="k">Refundable fee</td><td class="v">{{ $r->feeLabel() }}</td></tr>
            </table>
            <div class="noprint" style="text-align:center;">
                <button class="print" onclick="window.print()">Download / Print Receipt</button>
            </div>
        </div>
        <div class="foot">No. 1, Off Liberty Boulevard, Millionaire Quarters, Jos, Plateau State · (+234) 7012623680</div>
    </div>
</body>
</html>
