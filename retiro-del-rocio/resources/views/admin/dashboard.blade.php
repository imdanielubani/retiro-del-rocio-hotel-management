@php($subtitle = "Welcome back, ".auth()->user()->name." — here's what's happening today")

<x-admin.app title="Dashboard Overview" :subtitle="$subtitle">
    {{-- Dashboard content goes here. --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-6">
        <p class="text-sm text-[#6b7280]">
            Next up: build out the dashboard modules (bookings, rooms, users, CMS, etc.).
        </p>
    </div>
</x-admin.app>
