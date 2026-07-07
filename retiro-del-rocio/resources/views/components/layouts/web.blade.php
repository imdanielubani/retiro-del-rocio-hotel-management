<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php($metaDescription = $description ?? 'Retiro Del Rocio — a luxury retreat in Jos, Plateau State blending modern hospitality with intentional living. Book rooms and apartments, wellness experiences, dining, and curated escapes.')

    <title>{{ $title ?? 'Retiro Del Rocio' }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    {{-- Open Graph / social sharing --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Retiro Del Rocio">
    <meta property="og:title" content="{{ $title ?? 'Retiro Del Rocio' }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ asset('images/Hotel Logo 1.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? 'Retiro Del Rocio' }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">

    <link rel="icon" type="image/png" href="{{ asset('images/Hotel Logo 1.png') }}">

    {{-- Website fonts: Inter (body, closest free match to SF Pro), Poppins, Italianno (script) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Manrope:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&family=Italianno&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col overflow-x-hidden bg-[#242d22] font-sf text-white antialiased">
    <x-layouts.navbar />

    <main class="flex-1 overflow-x-hidden">
        {{ $slot }}
    </main>

    <x-layouts.footer />

    <x-toast />

    @livewireScripts
    @stack('scripts')
</body>
</html>
