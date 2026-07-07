<div class="relative" wire:poll.2s="popUnseen" x-data="{ notifOpen: false }" @keydown.escape.window="notifOpen = false">
    <button type="button" @click="notifOpen = !notifOpen; if (notifOpen) $wire.markRead()"
            class="relative flex size-9 items-center justify-center rounded-full transition hover:bg-[#f3f4f6]" aria-label="Notifications">
        <svg class="size-6 text-[#1e1e1e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
        </svg>
        @if ($notifCount > 0)
            <span class="absolute right-[7px] top-[7px] size-[7px] rounded-[3.5px] border-[0.8px] border-white bg-[#f38c00]"></span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="notifOpen" x-cloak x-transition.origin.top.right @click.outside="notifOpen = false"
         class="absolute right-0 z-50 mt-2 w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-[#f1f1ee] px-4 py-3">
            <p class="text-[14px] font-bold text-[#1e1e1e]">Notifications</p>
            @if ($notifCount > 0)
                <span class="rounded-full bg-[#fff3e0] px-2 py-0.5 text-[11px] font-semibold text-[#b45309]">{{ $notifCount }} new</span>
            @endif
        </div>

        <div class="max-h-[60vh] overflow-y-auto">
            @if ($messageNotifs->isEmpty() && $bookingNotifs->isEmpty() && $spaNotifs->isEmpty() && $gymNotifs->isEmpty() && $restoNotifs->isEmpty() && $cinemaNotifs->isEmpty())
                <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                    <div class="flex size-11 items-center justify-center rounded-full bg-[#f3f3ee]">
                        <svg class="size-5 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </div>
                    <p class="text-[13px] font-semibold text-[#1e1e1e]">You're all caught up</p>
                    <p class="text-[12px] text-[#9ca3af]">New messages and bookings will show here.</p>
                </div>
            @endif

            {{-- Contact message notifications --}}
            @if ($messageNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Messages</p>
                @foreach ($messageNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.messages.index') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[12px] font-bold text-[#6b7280]">{{ $d['initials'] ?? '?' }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['name'] ?? 'New enquiry' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['preview'] ?? 'New enquiry' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif

            {{-- Booking notifications --}}
            @if ($bookingNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Bookings</p>
                @foreach ($bookingNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.bookings.index') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full {{ ($d['has_pickup'] ?? false) ? 'bg-[#fff1e0] text-[#f38c00]' : 'bg-[#e7f6ec] text-[#16a34a]' }}">
                            @if ($d['has_pickup'] ?? false)
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
                            @else
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? 'New booking' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['room'] ?? 'New reservation' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif
            {{-- Spa booking notifications --}}
            @if ($spaNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Spa & Wellness</p>
                @foreach ($spaNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.spa.bookings') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f3e8ff] text-[#7c3aed]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 0-7 7c0 3 2 5 2 8h10c0-3 2-5 2-8a7 7 0 0 0-7-7z"/><path d="M9 22h6"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? 'New spa booking' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['service'] ?? 'New spa reservation' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif
            {{-- Gym membership notifications --}}
            @if ($gymNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Gym & Fitness</p>
                @foreach ($gymNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.gym.memberships') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#e7f6ec] text-[#16a34a]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5 17.5 17.5M4 9l-1 1 2 2M20 15l1-1-2-2M7 4 4 7l3 3M17 20l3-3-3-3"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? 'New member' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['plan'] ?? 'New gym membership' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif
            {{-- Restaurant reservation notifications --}}
            @if ($restoNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Restaurant</p>
                @foreach ($restoNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.restaurant.reservations') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#fff1e0] text-[#f38c00]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7a3 3 0 0 0 3 3v10M6 2v6M9 2v6M18 2c-1.5 1-2.5 3-2.5 6.5 0 2 1 3 2.5 3.5v10"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? 'New reservation' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['area'] ?? 'New restaurant reservation' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif
            {{-- Cinema booking notifications --}}
            @if ($cinemaNotifs->isNotEmpty())
                <p class="bg-[#fafaf7] px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-[#9ca3af]">Cinema</p>
                @foreach ($cinemaNotifs as $n)
                    @php $d = $n->data; @endphp
                    <a href="{{ $d['url'] ?? route('admin.cinema.bookings') }}" wire:navigate @click="notifOpen = false"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-[#f9fafb] {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#fef9c3] text-[#a16207]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? 'New cinema booking' }}</p>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['movie'] ?? 'New cinema booking' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($n->read_at)
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless
                    </a>
                @endforeach
            @endif
        </div>

        <a href="{{ route('admin.bookings.index') }}" wire:navigate @click="notifOpen = false"
           class="block border-t border-[#f1f1ee] px-4 py-3 text-center text-[13px] font-semibold text-[#ba6d04] transition hover:bg-[#f9fafb]">
            View all bookings
        </a>
    </div>
</div>
