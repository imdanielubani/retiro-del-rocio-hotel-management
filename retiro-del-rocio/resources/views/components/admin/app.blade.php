@props([
    'title' => 'Dashboard',
    'subtitle' => null,
])

@php
    $user = auth()->user();
    $initial = strtoupper(mb_substr($user->name ?? 'A', 0, 1));

    // Inner-SVG icon library (rendered inside a 24x24 stroke <svg>). Shared keys reuse the same glyph.
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'reception' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'visitor' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2"/><path d="M14 10h4M14 14h4"/>',
        'task' => '<rect x="6" y="4" width="12" height="16" rx="2"/><path d="M9 4h6v2H9zM9 13l1.5 1.5L14 11"/>',
        'guests' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'requests' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'history' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'apartments' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>',
        'restaurant' => '<path d="M7 2v20M5 2v6a2 2 0 0 0 4 0V2M17 2c-2 0-3 2-3 6s1 6 3 6v8"/>',
        'kitchen' => '<path d="M6 14h12v6a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1z"/><path d="M18 14a4 4 0 0 0 0-8 5 5 0 0 0-9.5-1A4 4 0 0 0 6 14"/>',
        'bar' => '<path d="M8 22h8M12 11v11M5 3h14l-7 8z"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/>',
        'truck' => '<rect x="1" y="6" width="15" height="11" rx="1"/><path d="M16 9h4l3 3v5h-7z"/><circle cx="5.5" cy="18" r="1.5"/><circle cx="18.5" cy="18" r="1.5"/>',
        'spa' => '<path d="M12 2C8 6 8 10 12 14c4-4 4-8 0-12zM5 14c2 2 5 2 7 0M12 14c2 2 5 2 7 0M12 14v8"/>',
        'cinema' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
        'gym' => '<path d="M6 7v10M18 7v10M3 9v6M21 9v6M6 12h12"/>',
        'car' => '<path d="M5 13l1.4-4.2A2 2 0 0 1 8.3 7h7.4a2 2 0 0 1 1.9 1.3L19 13M5 13h14v4H5z"/><circle cx="7.5" cy="17.5" r="1.3"/><circle cx="16.5" cy="17.5" r="1.3"/>',
        'plans' => '<path d="M12 2 2 7l10 5 10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
        'discounts' => '<circle cx="12" cy="12" r="9"/><path d="M9 9h.01M15 15h.01M16 8 8 16"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'cleaning' => '<path d="M3 21l6-6M13 3l8 8-3 3-8-8zM9 9l6 6"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.3 2.3-2-2z"/>',
        'shield' => '<path d="M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'tablet' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'tv' => '<rect x="2" y="5" width="20" height="13" rx="2"/><path d="M8 21h8M12 18v3"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'door' => '<path d="M3 21h18M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17M14 12h.01"/>',
        'payments' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'billing' => '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2zM8 8h8M8 12h8M8 16h5"/>',
        'reports' => '<path d="M3 3v18h18M8 17V9M13 17V5M18 17v-6"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'chat' => '<path d="M21 11.5a8.5 8.5 0 0 1-12 7.7L3 21l1.8-6A8.5 8.5 0 1 1 21 11.5z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.6a2 2 0 0 1-.5 2.1L8 9.5a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.7 2z"/>',
        'templates' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'cms' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/>',
        'roles' => '<path d="M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.8 1.2V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 8 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 4.6 15H4.5a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 11 4.6V4.5a2 2 0 0 1 4 0v.1A1.6 1.6 0 0 0 16 6a1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A1.6 1.6 0 0 0 19.4 11H21a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
    ];

    $dashboard = ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')];

    // Grouped navigation. Items with `children` are expandable; others are direct links.
    // Links use '#' placeholders until their module routes exist.
    $nav = [
        ['label' => 'Operations Management', 'items' => [
            ['label' => 'Reception', 'icon' => 'reception', 'href' => '#'],
            ['label' => 'Visitor Pass', 'icon' => 'visitor', 'href' => '#'],
            ['label' => 'Task Center', 'icon' => 'task', 'href' => '#'],
        ]],
        ['label' => 'Guest Management', 'items' => [
            ['label' => 'Guests', 'icon' => 'guests', 'href' => '#'],
            ['label' => 'Service Requests', 'icon' => 'requests', 'href' => '#'],
            ['label' => 'Stay History', 'icon' => 'history', 'href' => '#'],
        ]],
        ['label' => 'Property Management', 'items' => [
            ['key' => 'apartments', 'label' => 'Apartments', 'icon' => 'apartments', 'children' => [
                ['label' => 'Rooms', 'href' => route('admin.rooms.index'), 'active' => request()->routeIs('admin.rooms.*')],
                ['label' => 'Bookings', 'href' => route('admin.bookings.index'), 'active' => request()->routeIs('admin.bookings.*')],
            ]],
        ]],
        ['label' => 'Restaurant Management', 'items' => [
            ['key' => 'restaurant', 'label' => 'Restaurant', 'icon' => 'restaurant', 'children' => [
                ['label' => 'Tables', 'href' => route('admin.restaurant.tables'), 'active' => request()->routeIs('admin.restaurant.tables')],
                ['label' => 'Lounge', 'href' => route('admin.restaurant.lounge'), 'active' => request()->routeIs('admin.restaurant.lounge')],
                ['label' => 'Reservations', 'href' => route('admin.restaurant.reservations'), 'active' => request()->routeIs('admin.restaurant.reservations')],
            ]],
            ['key' => 'kitchen', 'label' => 'Kitchen', 'icon' => 'kitchen', 'children' => [
                ['label' => 'Menu', 'href' => '#'],
                ['label' => 'Orders', 'href' => '#'],
            ]],
            ['key' => 'bar', 'label' => 'Bar & Lounge', 'icon' => 'bar', 'children' => [
                ['label' => 'Drinks Menu', 'href' => '#'],
                ['label' => 'Orders', 'href' => '#'],
            ]],
        ]],
        ['label' => 'Inventory Management', 'items' => [
            ['label' => 'Kitchen Inventory', 'icon' => 'box', 'href' => '#'],
            ['label' => 'Bar Inventory', 'icon' => 'box', 'href' => '#'],
            ['label' => 'Spa Inventory', 'icon' => 'box', 'href' => '#'],
            ['label' => 'Cinema Inventory', 'icon' => 'box', 'href' => '#'],
            ['label' => 'Housekeeping Inventory', 'icon' => 'box', 'href' => '#'],
            ['label' => 'Suppliers', 'icon' => 'truck', 'href' => '#'],
        ]],
        ['label' => 'Facility Management', 'items' => [
            ['key' => 'spa', 'label' => 'Spa & Wellness', 'icon' => 'spa', 'children' => [
                ['label' => 'Services', 'href' => route('admin.spa.services'), 'active' => request()->routeIs('admin.spa.services')],
                ['label' => 'Appointments', 'href' => route('admin.spa.bookings'), 'active' => request()->routeIs('admin.spa.bookings')],
            ]],
            ['key' => 'cinema', 'label' => 'Cinema', 'icon' => 'cinema', 'children' => [
                ['label' => 'Movies', 'href' => route('admin.cinema.movies'), 'active' => request()->routeIs('admin.cinema.movies')],
                ['label' => 'Snacks', 'href' => route('admin.cinema.snacks'), 'active' => request()->routeIs('admin.cinema.snacks')],
                ['label' => 'Bookings', 'href' => route('admin.cinema.bookings'), 'active' => request()->routeIs('admin.cinema.bookings')],
            ]],
            ['key' => 'gym', 'label' => 'Gym', 'icon' => 'gym', 'children' => [
                ['label' => 'Plans', 'href' => route('admin.gym.plans'), 'active' => request()->routeIs('admin.gym.plans')],
                ['label' => 'Memberships', 'href' => route('admin.gym.memberships'), 'active' => request()->routeIs('admin.gym.memberships')],
                ['label' => 'Access Logs', 'href' => '#'],
            ]],
            ['key' => 'vehicle-pickups', 'label' => 'Vehicle Pickups', 'icon' => 'car', 'children' => [
                ['label' => 'Vehicles', 'href' => route('admin.vehicles.index'), 'active' => request()->routeIs('admin.vehicles.index')],
                ['label' => 'Bookings', 'href' => route('admin.vehicles.bookings'), 'active' => request()->routeIs('admin.vehicles.bookings')],
            ]],
        ]],
        ['label' => 'Membership Management', 'items' => [
            ['label' => 'Plans', 'icon' => 'plans', 'href' => '#'],
            ['label' => 'Members', 'icon' => 'guests', 'href' => '#'],
            ['label' => 'Discounts', 'icon' => 'discounts', 'href' => '#'],
            ['label' => 'Access Logs', 'icon' => 'list', 'href' => '#'],
        ]],
        ['label' => 'Staff Management', 'items' => [
            ['label' => 'Housekeeping', 'icon' => 'cleaning', 'href' => '#'],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'wrench', 'children' => [
                ['label' => 'Tasks', 'href' => '#'],
                ['label' => 'Assets', 'href' => '#'],
                ['label' => 'Service History', 'href' => '#'],
            ]],
            ['label' => 'Security', 'icon' => 'shield', 'href' => '#'],
            ['label' => 'Staff', 'icon' => 'user', 'href' => '#'],
            ['label' => 'Departments', 'icon' => 'dashboard', 'href' => '#'],
        ]],
        ['label' => 'Device Management', 'items' => [
            ['label' => 'Tablets', 'icon' => 'tablet', 'href' => '#'],
            ['label' => 'Smart TVs', 'icon' => 'tv', 'href' => '#'],
        ]],
        ['label' => 'Access Control', 'items' => [
            ['label' => 'Gate Pass', 'icon' => 'lock', 'href' => route('admin.ttlock.locks'), 'active' => request()->routeIs('admin.ttlock.*')],
            ['label' => 'RFID Cards', 'icon' => 'card', 'href' => '#'],
            ['label' => 'Visitor Access', 'icon' => 'door', 'href' => '#'],
            ['label' => 'Access Logs', 'icon' => 'list', 'href' => '#'],
        ]],
        ['label' => 'Analytics Management', 'items' => [
            ['label' => 'Payments', 'icon' => 'payments', 'href' => route('admin.payment.index'), 'active' => request()->routeIs('admin.payment.*')],
            ['label' => 'Billing', 'icon' => 'billing', 'href' => '#'],
            ['label' => 'Reports', 'icon' => 'reports', 'href' => '#'],
            ['label' => 'Activity Logs', 'icon' => 'activity', 'href' => '#'],
        ]],
        ['label' => 'Communication', 'items' => [
            ['label' => 'Chat', 'icon' => 'chat', 'href' => '#'],
            ['label' => 'Messages', 'icon' => 'mail', 'href' => route('admin.messages.index'), 'active' => request()->routeIs('admin.messages.*')],
            ['label' => 'Intercom', 'icon' => 'phone', 'href' => '#'],
            ['label' => 'Notifications', 'icon' => 'requests', 'href' => '#'],
            ['label' => 'Templates', 'icon' => 'templates', 'href' => '#'],
        ]],
        ['label' => 'Administration', 'items' => [
            ['key' => 'website-cms', 'label' => 'Website CMS', 'icon' => 'cms', 'children' => [
                ['label' => 'Contents', 'href' => route('admin.cms.index'), 'active' => request()->routeIs('admin.cms.*')],
            ]],
            ['label' => 'Roles & Permissions', 'icon' => 'roles', 'href' => '#'],
            ['label' => 'Settings', 'icon' => 'settings', 'href' => '#'],
        ]],
    ];

    // Which expandable group should start open (the one containing the active route).
    $activeMenu = collect($nav)->pluck('items')->flatten(1)
        ->first(fn ($item) => ! empty($item['children']) && collect($item['children'])->contains(fn ($c) => $c['active'] ?? false))['key'] ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/Hotel Logo 1.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-[#222a1f] text-[#1e1e1e] antialiased">
    <div
        x-data="{
            collapsed: JSON.parse(localStorage.getItem('sidebarCollapsed') ?? 'false'),
            open: @js($activeMenu),
            mobileOpen: false,
            isDesktop: window.matchMedia('(min-width: 1024px)').matches,
            get mini() { return this.collapsed && this.isDesktop },
            toggleMenu(key) { this.open = this.open === key ? null : key },
            init() {
                this.$watch('collapsed', v => localStorage.setItem('sidebarCollapsed', JSON.stringify(v)));
                const mq = window.matchMedia('(min-width: 1024px)');
                mq.addEventListener('change', e => { this.isDesktop = e.matches; if (e.matches) this.mobileOpen = false; });
            },
        }"
        class="flex h-screen overflow-hidden"
    >
        {{-- Mobile backdrop --}}
        <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
        {{-- ===== Sidebar ===== --}}
        <aside
            :class="{
                'max-lg:translate-x-0!': mobileOpen,
                'lg:w-[88px]': mini,
                'lg:w-[280px]': !mini,
            }"
            class="fixed inset-y-0 left-0 z-50 flex w-[280px] shrink-0 flex-col overflow-hidden bg-[#222a1f] px-4 py-4 transition-transform duration-300 ease-in-out max-lg:-translate-x-full lg:static lg:z-auto lg:translate-x-0 lg:bg-transparent lg:transition-[width]"
        >
            {{-- Logo (static) --}}
            <div class="flex shrink-0 items-center justify-center py-1">
                <img x-show="!mini" x-cloak src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-20 w-auto object-contain">
                <img x-show="mini" x-cloak src="{{ asset('images/Hotel Logo 1.png') }}" alt="" class="h-9 w-auto object-contain">
            </div>

            {{-- Demarcation line above the navigation --}}
            <div class="my-2 h-px w-full shrink-0 bg-white/20"></div>

            {{-- Nav (grouped sections; scrolls when the viewport is too short to fit everything) --}}
            <nav id="adminNav" class="no-scrollbar mt-2 flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto">
                {{-- Dashboard --}}
                <a href="{{ $dashboard['href'] }}" wire:navigate @click="mobileOpen = false"
                   @class([
                       'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium transition',
                       'bg-[#3a4631] text-white' => $dashboard['active'],
                       'text-[#c7cfc0] hover:bg-white/5 hover:text-white' => ! $dashboard['active'],
                   ])
                   :class="mini ? 'justify-center' : ''"
                   x-bind:title="mini ? '{{ $dashboard['label'] }}' : ''">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$dashboard['icon']] !!}</svg>
                    <span x-show="!mini" x-cloak>{{ $dashboard['label'] }}</span>
                </a>

                @foreach ($nav as $section)
                    {{-- Section label --}}
                    <p x-show="!mini" x-cloak class="mt-3 mb-0.5 px-3 text-[11px] font-semibold uppercase tracking-[0.4px] text-[#7d8a72]">{{ $section['label'] }}</p>
                    {{-- Mini divider stand-in for the section label --}}
                    <div x-show="mini" x-cloak class="mx-auto my-1.5 h-px w-6 bg-white/10"></div>

                    @foreach ($section['items'] as $item)
                        @if (! empty($item['children']))
                            {{-- Expandable item --}}
                            <button type="button" @click="toggleMenu('{{ $item['key'] }}')"
                                    @class([
                                        'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium transition',
                                        'bg-[#3a4631] text-white' => $activeMenu === $item['key'],
                                        'text-[#c7cfc0] hover:bg-white/5 hover:text-white' => $activeMenu !== $item['key'],
                                    ])
                                    :class="mini ? 'justify-center' : ''"
                                    x-bind:title="mini ? '{{ $item['label'] }}' : ''">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] ?? '' !!}</svg>
                                <span x-show="!mini" x-cloak class="flex-1 text-left">{{ $item['label'] }}</span>
                                <svg x-show="!mini" x-cloak class="size-4 shrink-0 transition-transform duration-200"
                                     :class="open === '{{ $item['key'] }}' ? '-rotate-180' : ''"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            {{-- Submenu --}}
                            <div x-show="!mini && open === '{{ $item['key'] }}'" x-collapse x-cloak
                                 class="flex flex-col rounded-xl bg-white/5 p-1.5">
                                @foreach ($item['children'] as $child)
                                    <a href="{{ $child['href'] }}" @if(($child['href'] ?? '#') !== '#') wire:navigate @endif @click="mobileOpen = false"
                                       @class([
                                           'rounded-lg px-4 py-2 text-[14px] transition',
                                           'bg-white/10 font-semibold text-white' => $child['active'] ?? false,
                                           'text-[#c7cfc0] hover:bg-white/5 hover:text-white' => ! ($child['active'] ?? false),
                                       ])>
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            {{-- Direct link --}}
                            <a href="{{ $item['href'] }}" @if(($item['href'] ?? '#') !== '#') wire:navigate @endif @click="mobileOpen = false"
                               @class([
                                   'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium transition',
                                   'bg-[#3a4631] text-white' => $item['active'] ?? false,
                                   'text-[#c7cfc0] hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                               ])
                               :class="mini ? 'justify-center' : ''"
                               x-bind:title="mini ? '{{ $item['label'] }}' : ''">
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] ?? '' !!}</svg>
                                <span x-show="!mini" x-cloak>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                @endforeach
            </nav>

            {{-- Footer (static) --}}
            <div class="mt-3 flex shrink-0 flex-col gap-1.5">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-xl bg-[#f38c00] px-3 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]"
                            :class="mini ? 'justify-center' : ''"
                            x-bind:title="mini ? 'Logout' : ''">
                        <img src="{{ asset('images/logout.png') }}" alt="" class="size-5 shrink-0">
                        <span x-show="!mini" x-cloak>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- ===== Main panel ===== --}}
        <main class="m-3 flex flex-1 flex-col overflow-hidden rounded-[20px] bg-[#f3f3ee] sm:rounded-[28px] lg:ml-0">
            {{-- Topbar --}}
            <header class="m-3 flex items-center justify-between gap-3 rounded-[15px] border border-[#e5e5e5]/95 bg-white px-3.5 py-2.5 sm:m-4 sm:gap-4">
                <div class="flex min-w-0 items-center gap-3 sm:gap-[15px]">
                    {{-- Mobile: open drawer --}}
                    <button type="button" @click="mobileOpen = true"
                            class="flex size-8 shrink-0 items-center justify-center rounded-md border border-[#ededed] bg-white text-[#1e1e1e] transition hover:bg-[#f9fafb] lg:hidden"
                            aria-label="Open menu">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18M3 12h18M3 18h18" />
                        </svg>
                    </button>
                    {{-- Desktop: collapse toggle --}}
                    <button type="button" @click="collapsed = !collapsed"
                            class="hidden size-8 shrink-0 items-center justify-center rounded-md border border-[#ededed] bg-white text-[#1e1e1e] transition hover:bg-[#f9fafb] lg:flex"
                            aria-label="Toggle sidebar">
                        <svg class="size-4 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m17 7-5 5 5 5M11 7l-5 5 5 5" />
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-[17px] font-bold leading-tight text-[#1e1e1e] sm:text-[20px]">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-[13px]">
                    {{-- Search --}}
                    <div class="relative hidden md:block">
                        <svg class="pointer-events-none absolute left-[17px] top-1/2 size-[18px] -translate-y-1/2 text-[#9f9f9f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                        <input type="text" placeholder="Search pages..."
                               class="h-[37px] w-[240px] rounded-full border border-[#e5e5e5]/90 bg-[#f5f5f0] pl-[44px] pr-4 text-[14px] text-[#1e1e1e] placeholder:text-[#9f9f9f] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20 lg:w-[312px]">
                    </div>

                    {{-- Notifications (real-time, polled) --}}
                    <livewire:admin.notifications.bell />

                    {{-- User --}}
                    <div class="hidden items-center gap-[10px] sm:flex">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-[18px] bg-[#f38c00] text-[18px] font-bold leading-none text-white">{{ $initial }}</div>
                        <div class="hidden leading-tight md:block">
                            <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $user->name }}</p>
                            <p class="text-[12px] text-[#99a694]">{{ $user->email }}</p>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-3 pb-3 sm:px-4 sm:pb-4">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-toast />

    @livewireScripts

    {{-- Keep the sidebar's scroll position across wire:navigate page loads (don't jump to top). --}}
    <script>
        if (! window.__adminNavScrollBound) {
            window.__adminNavScrollBound = true;
            document.addEventListener('livewire:navigating', () => {
                const nav = document.getElementById('adminNav');
                if (nav) sessionStorage.setItem('adminNavScroll', nav.scrollTop);
            });
            document.addEventListener('livewire:navigated', () => {
                const nav = document.getElementById('adminNav');
                const y = sessionStorage.getItem('adminNavScroll');
                if (nav && y !== null) nav.scrollTop = parseInt(y, 10);
            });
        }
    </script>
</body>
</html>
