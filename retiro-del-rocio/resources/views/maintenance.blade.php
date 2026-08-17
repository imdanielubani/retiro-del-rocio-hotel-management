<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ \App\Support\HotelSettings::get('hotel.name', config('app.name')) }} — Under Maintenance</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #16130f;
            color: #f5f1ea;
        }
        .card {
            max-width: 480px;
            text-align: center;
        }
        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(243, 140, 0, 0.15);
            color: #f38c00;
        }
        h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 10px;
            color: #ffffff;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: rgba(245, 241, 234, 0.7);
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
        </div>
        <h1>We'll be right back</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
