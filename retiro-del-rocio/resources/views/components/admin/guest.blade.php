<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin Portal' }} — Retiro Del Rocio</title>

    <link rel="icon" type="image/png" href="{{ asset('images/Hotel Logo 1.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased bg-white text-[#1e1e1e]">
    <div class="flex min-h-screen">
        {{-- Brand panel --}}
        <aside class="relative hidden w-[42%] max-w-[560px] shrink-0 overflow-hidden bg-[#222a1f] lg:flex lg:flex-col">
            {{-- Accent bar --}}
            <span class="absolute inset-y-0 left-0 w-3 bg-[#f38c00]"></span>

            <div class="flex flex-1 flex-col px-16 py-12">
                <img src="{{ asset('images/Hotel Logo 1.png') }}" alt="Retiro Del Rocio"
                     width="95" height="55"
                     class="h-auto w-[95px] self-start object-contain">

                <div class="mt-auto">
                    <h1 class="text-[64px] font-bold leading-none text-[#f38c00]">RETIRO</h1>
                    <h2 class="mt-3 text-[40px] font-bold leading-none text-white">DEL ROCIO</h2>

                    <span class="mt-6 block h-1 w-20 rounded-full bg-[#f38c00]"></span>

                    <p class="mt-6 text-sm text-[#b8c2b2]">Administration Portal</p>

                    <ul class="mt-10 space-y-6 text-[13px] text-[#d1dbcc]">
                        <li class="flex items-center gap-3">
                            <span class="size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                            Manage all hotel operations
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                            Real-time bookings &amp; revenue
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                            Content &amp; guest management
                        </li>
                    </ul>
                </div>

                <p class="mt-auto pt-12 text-[11px] text-[#73806e]">
                    &copy; {{ date('Y') }} Retiro Del Rocio. All rights reserved.
                </p>
            </div>
        </aside>

        {{-- Form panel --}}
        <main class="flex flex-1 items-center justify-center px-6 py-12">
            <div class="w-full max-w-[440px]">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-toast />

    @livewireScripts
</body>
</html>
