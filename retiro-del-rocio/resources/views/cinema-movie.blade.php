<x-layouts.web :title="$movie->title.' — Retiro Del Rocio'" :description="\Illuminate\Support\Str::limit($movie->synopsis, 150)">

    @php
        $cinemaSuccess = session('cinema_success');
        session()->forget('cinema_success');
        $paystackKey = config('services.paystack.public_key');

        $movieJson = [
            'title' => $movie->title, 'slug' => $movie->slug,
            'showtimes' => $movie->showtimeList(),
            'poster' => $movie->posterUrl(),
            'duration' => $movie->duration, 'rating' => $movie->rating,
            'room_price' => $movie->room_price,
        ];
        $snacksJson = $snacks->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'price' => $s->price, 'image' => $s->imageUrl(),
        ])->values();
    @endphp

    <div class="bg-[#0e0e10]"
         x-data="cinemaBooking({
            movie: @js($movieJson),
            snacks: @js($snacksJson),
            paystackKey: @js($paystackKey),
            successData: @js($cinemaSuccess),
            seatsUrl: @js(url('cinema/'.$movie->slug.'/seats')),
            holdUrl: @js(route('cinema.hold')),
            releaseUrl: @js(route('cinema.release')),
            csrf: @js(csrf_token()),
            rooms: @js(\App\Models\Movie::ROOMS),
            seatsPerRoom: @js(\App\Models\Movie::SEATS_PER_ROOM),
         })">

        {{-- ============================ MOVIE HERO ============================ --}}
        <section class="relative w-full overflow-hidden">
            <x-img src="{{ $movie->backdropUrl() }}" alt="{{ $movie->title }}" sizes="100vw" loading="eager" fetchpriority="high"
                   class="absolute inset-0 h-full w-full scale-110 object-cover blur-sm" />
            <div class="absolute inset-0 bg-black/75"></div>
            <x-layouts.container class="relative flex flex-col gap-8 py-12 lg:flex-row lg:items-center lg:gap-12 lg:py-16">
                <x-img src="{{ $movie->posterUrl() }}" alt="{{ $movie->title }}" sizes="(min-width:1024px) 320px, 60vw"
                       class="h-[360px] w-[240px] shrink-0 rounded-[16px] object-cover shadow-2xl lg:h-[440px] lg:w-[300px]" />
                <div class="flex flex-col gap-5 text-white">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-h1">{{ $movie->title }}</h1>
                    <div class="flex flex-wrap items-center gap-3 text-label text-white/80">
                        @if ($movie->rating)<span class="rounded border border-white/30 px-2 py-0.5 text-[11px] font-semibold">{{ $movie->rating }}</span>@endif
                        <span>{{ $movie->genre }}</span>
                        @if ($movie->duration)<span class="flex items-center gap-1"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>{{ $movie->duration }}</span>@endif
                        <span class="rounded bg-[#f38c00] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">{{ $movie->classificationLabel() }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <p class="text-title font-semibold">{{ cms('cinemamovie.summary_label') }}</p>
                        <p class="max-w-[640px] text-body leading-relaxed text-white/75">{{ $movie->synopsis }}</p>
                    </div>
                    @if ($movie->trailer_url)
                        <div>
                            <a href="{{ $movie->trailer_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2.5 rounded-[10px] bg-[#e50914] px-7 py-3 text-body-lg font-semibold text-white transition hover:bg-[#c20710]">
                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                {{ cms('cinemamovie.trailer_label') }}
                            </a>
                        </div>
                    @endif
                </div>
            </x-layouts.container>
        </section>

        {{-- Offer ticker --}}
        <div class="w-full bg-[#1b1b18] py-3">
            <x-layouts.container class="flex flex-wrap items-center justify-center gap-2 text-center">
                <span class="text-body font-semibold text-white">{{ cms('cinema.offer_text') }}</span>
                <span class="text-label text-[#f38c00]">{{ cms('cinema.offer_terms') }}</span>
            </x-layouts.container>
        </div>

        {{-- ============================ BOOKING ============================ --}}
        <section class="w-full py-12 lg:py-16">
            <x-layouts.container class="flex flex-col gap-10">

                {{-- Date --}}
                <div class="flex flex-col gap-4">
                    <p class="text-title font-semibold text-white">{{ cms('cinemamovie.date_label') }}</p>
                    <div class="no-scrollbar flex items-stretch gap-3 overflow-x-auto pb-1">
                        <template x-for="d in dates" :key="d.iso">
                            <button type="button" @click="selectedDate = d.iso"
                                    class="flex w-[84px] shrink-0 flex-col items-center justify-center gap-0.5 rounded-[12px] border py-3 transition"
                                    :class="selectedDate === d.iso ? 'border-[#f38c00] bg-[#f38c00] text-white' : 'border-white/15 bg-[#1b1b18] text-white/70 hover:border-white/40'">
                                <span class="text-label" x-text="d.label"></span>
                                <span class="text-xl font-bold" x-text="d.day"></span>
                                <span class="text-[11px]" x-text="d.mon"></span>
                            </button>
                        </template>

                        {{-- Calendar: jump to any further date (opens a popup) --}}
                        <button type="button" @click="openCalendar()"
                                class="flex w-[84px] shrink-0 flex-col items-center justify-center gap-1.5 rounded-[12px] border border-white/15 bg-[#1b1b18] py-3 text-white/70 transition hover:border-white/40">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke-linecap="round"/></svg>
                            <span class="text-[11px]">Calendar</span>
                        </button>
                    </div>

                    {{-- Calendar popup --}}
                    <div x-show="showCalendar" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4"
                         x-transition.opacity @keydown.escape.window="showCalendar = false">
                        <div class="absolute inset-0 bg-black/70" @click="showCalendar = false"></div>
                        <div class="relative z-10 w-full max-w-[360px] rounded-[18px] border border-white/10 bg-[#15151a] p-6 shadow-2xl"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                            <div class="flex items-center justify-between">
                                <p class="text-body-lg font-semibold text-white" x-text="calMonthLabel"></p>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="calShift(-1)" aria-label="Previous month" class="flex size-9 items-center justify-center rounded-full border border-white/20 text-white transition hover:bg-white/10">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                    </button>
                                    <button type="button" @click="calShift(1)" aria-label="Next month" class="flex size-9 items-center justify-center rounded-full border border-white/20 text-white transition hover:bg-white/10">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-5 grid grid-cols-7 gap-1 text-center text-label text-white/40">
                                <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d"><span x-text="d"></span></template>
                            </div>
                            <div class="mt-2 grid grid-cols-7 gap-1">
                                <template x-for="(cell, i) in calGrid" :key="i">
                                    <div>
                                        <template x-if="cell.blank"><span class="block"></span></template>
                                        <template x-if="!cell.blank">
                                            <button type="button" @click="pickDay(cell)" :disabled="cell.past"
                                                    class="flex aspect-square w-full items-center justify-center rounded-[8px] text-body transition"
                                                    :class="cell.past ? 'cursor-not-allowed text-white/20' : (cell.selected ? 'bg-[#f38c00] font-semibold text-white' : (cell.isToday ? 'border border-[#f38c00]/60 text-white hover:bg-white/10' : 'text-white/80 hover:bg-white/10'))"
                                                    x-text="cell.day"></button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="showCalendar = false" class="mt-5 w-full rounded-[10px] border border-white/20 py-2.5 text-body font-medium text-white/70 transition hover:bg-white/10">Close</button>
                        </div>
                    </div>
                </div>

                {{-- Time --}}
                <div class="flex flex-col gap-4">
                    <p class="text-title font-semibold text-white">{{ cms('cinemamovie.time_label') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <template x-for="t in movie.showtimes" :key="t">
                            <button type="button" @click="selectedTime = t"
                                    class="rounded-[10px] border px-5 py-2.5 text-body font-medium transition"
                                    :class="selectedTime === t ? 'border-[#f38c00] bg-[#f38c00] text-white' : 'border-white/15 bg-[#1b1b18] text-white/70 hover:border-white/40'"
                                    x-text="t"></button>
                        </template>
                    </div>
                </div>

                {{-- Choose a private room --}}
                <div class="flex flex-col gap-5">
                    <div class="flex flex-col gap-1">
                        <p class="text-title font-semibold text-white">{{ cms('cinemamovie.seats_label') }}</p>
                        <p class="text-label text-white/50">Book a private cinema room — {{ \App\Models\Movie::SEATS_PER_ROOM }} seats all to yourself, {{ $movie->roomPriceLabel() }} per room.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <template x-for="room in rooms" :key="room">
                            <button type="button" @click="selectRoom(room)" :disabled="isRoomTaken(room)"
                                    class="relative flex flex-col gap-5 rounded-[16px] border p-6 text-left transition"
                                    :class="isRoomTaken(room) ? 'cursor-not-allowed border-white/10 bg-white/[0.04] opacity-60' : (selectedRoom === room ? 'border-[#f38c00] bg-[#f38c00]/10' : 'border-white/15 bg-[#1b1b18] hover:border-white/40')">
                                <div class="flex items-center justify-between">
                                    <span class="text-body-lg font-semibold text-white" x-text="room"></span>
                                    <span x-show="isRoomTaken(room)" class="rounded-full bg-white/10 px-2.5 py-0.5 text-[11px] font-semibold text-white/60">Booked</span>
                                    <span x-show="!isRoomTaken(room) && selectedRoom === room" x-cloak class="flex size-6 items-center justify-center rounded-full bg-[#f38c00] text-white">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </span>
                                </div>
                                {{-- Screen + 4 private seats --}}
                                <div class="flex flex-col items-center gap-4 rounded-[12px] bg-black/20 py-5">
                                    <div class="h-1 w-[60%] rounded-full bg-gradient-to-r from-transparent via-[#f38c00]/70 to-transparent"></div>
                                    <div class="flex justify-center gap-3">
                                        <template x-for="n in seatsPerRoom" :key="n">
                                            <svg class="size-8" viewBox="0 0 24 24" fill="currentColor"
                                                 :class="isRoomTaken(room) ? 'text-white/20' : (selectedRoom === room ? 'text-[#f38c00]' : 'text-white/55')">
                                                <path d="M5 11V7a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v4a2 2 0 0 0-2 2v3H7v-3a2 2 0 0 0-2-2zM5 13a1 1 0 0 1 1 1v5H4v-5a1 1 0 0 1 1-1zm14 0a1 1 0 0 1 1 1v5h-2v-5a1 1 0 0 1 1-1z"/>
                                            </svg>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-t border-white/10 pt-3">
                                    <span class="text-label text-white/50" x-text="seatsPerRoom + ' private seats'"></span>
                                    <span class="text-body font-semibold text-[#f38c00]">{{ $movie->roomPriceLabel() }}</span>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- Guests --}}
                    <div class="flex items-center justify-between rounded-[12px] border border-white/12 bg-[#1b1b18] px-5 py-4 sm:max-w-[340px]">
                        <div class="flex flex-col">
                            <span class="text-body font-semibold text-white">Guests</span>
                            <span class="text-label text-white/50" x-text="'Up to ' + seatsPerRoom + ' in your room'"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="guests = Math.max(1, guests - 1)" class="flex size-8 items-center justify-center rounded-full border border-white/25 text-white transition hover:bg-white/10">−</button>
                            <span class="w-6 text-center text-body-lg font-semibold text-white" x-text="guests"></span>
                            <button type="button" @click="guests = Math.min(seatsPerRoom, guests + 1)" class="flex size-8 items-center justify-center rounded-full bg-[#f38c00] text-white transition hover:bg-[#dd7f00]">+</button>
                        </div>
                    </div>
                </div>

                {{-- Snacks (carousel) --}}
                @if ($snacks->isNotEmpty())
                    <div class="flex flex-col gap-5"
                         x-data="{ canL: false, canR: true,
                                   refresh() { const t = $refs.snackTrack; if (!t) return; this.canL = t.scrollLeft > 4; this.canR = (t.scrollLeft + t.clientWidth) < (t.scrollWidth - 4); },
                                   slide(dir) { const t = $refs.snackTrack; const c = t.querySelector('[data-snack]'); const amt = c ? (c.offsetWidth + 16) : 200; t.scrollBy({ left: dir * amt, behavior: 'smooth' }); } }"
                         x-init="$nextTick(() => refresh())">
                        <div class="flex items-end justify-between gap-4">
                            <p class="text-title font-semibold text-white">{{ cms('cinemamovie.snacks_label') }} <span class="text-label font-normal text-white/45">(Optional)</span></p>
                            <div class="hidden items-center gap-2 lg:flex">
                                <button type="button" @click="slide(-1)" :disabled="!canL" aria-label="Previous" class="flex size-9 items-center justify-center rounded-full border border-white/20 text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                </button>
                                <button type="button" @click="slide(1)" :disabled="!canR" aria-label="Next" class="flex size-9 items-center justify-center rounded-full border border-white/20 text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-30">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            </div>
                        </div>
                        <div x-ref="snackTrack" @scroll.debounce.50ms="refresh()" class="no-scrollbar flex snap-x gap-4 overflow-x-auto pb-1">
                            <template x-for="s in snacks" :key="s.id">
                                <div data-snack class="flex w-[168px] shrink-0 snap-start flex-col items-center gap-3 rounded-[14px] border bg-[#1b1b18] p-4 text-center transition"
                                     :class="(snackQty[s.id] || 0) > 0 ? 'border-[#f38c00]' : 'border-white/12'">
                                    <img :src="s.image" :alt="s.name" class="h-16 w-16 object-contain" loading="lazy">
                                    <p class="text-body font-semibold text-white" x-text="s.name"></p>
                                    <p class="text-label text-[#f38c00]" x-text="money(s.price)"></p>
                                    <div class="flex items-center gap-3">
                                        <button type="button" @click="snackQty[s.id] = Math.max(0, (snackQty[s.id]||0) - 1)" class="flex size-7 items-center justify-center rounded-full border border-white/25 text-white transition hover:bg-white/10">−</button>
                                        <span class="w-5 text-center text-body font-semibold text-white" x-text="snackQty[s.id] || 0"></span>
                                        <button type="button" @click="snackQty[s.id] = (snackQty[s.id]||0) + 1" class="flex size-7 items-center justify-center rounded-full bg-[#f38c00] text-white transition hover:bg-[#dd7f00]">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                {{-- Order details --}}
                <div class="flex flex-col gap-4 rounded-[16px] border border-white/10 bg-[#15151a] p-6 lg:p-8">
                    <p class="text-h3 font-semibold tracking-tight text-white">{{ cms('cinemamovie.order_label') }}</p>
                    <div class="flex flex-col gap-2.5 text-body text-white/80">
                        <p class="font-medium text-[#f38c00]">Private Room</p>
                        <div class="flex justify-between">
                            <span x-text="selectedRoom ? (selectedRoom + ' · ' + guests + ' guest' + (guests > 1 ? 's' : '')) : 'No room selected'"
                                  :class="selectedRoom ? '' : 'text-white/45'"></span>
                            <span class="font-medium text-white" x-text="money(roomPrice)"></span>
                        </div>
                    </div>
                    <template x-if="chosenSnacks.length">
                        <div class="flex flex-col gap-2.5 border-t border-white/10 pt-4 text-body text-white/80">
                            <p class="font-medium text-[#f38c00]">Food &amp; Drinks</p>
                            <template x-for="s in chosenSnacks" :key="s.name">
                                <div class="flex justify-between"><span x-text="s.name + ' (×' + s.qty + ')'"></span><span class="font-medium text-white" x-text="money(s.qty * s.price)"></span></div>
                            </template>
                        </div>
                    </template>
                    <div class="flex flex-col gap-2.5 border-t border-white/10 pt-4 text-body text-white/80">
                        <div class="flex justify-between"><span class="font-medium text-[#f38c00]">Convenience Fee</span><span class="font-medium text-white" x-text="money(fee)"></span></div>
                        <div class="flex justify-between"><span class="font-medium text-[#f38c00]">Taxes</span><span class="font-medium text-white" x-text="money(taxes)"></span></div>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/15 pt-4">
                        <span class="text-body-lg font-medium text-[#f38c00]">TOTAL</span>
                        <span class="text-h3 font-semibold tracking-tight text-white" x-text="money(grandTotal)"></span>
                    </div>
                    <button type="button" @click="openCheckout()" :disabled="holding" class="mt-2 w-full rounded-[10px] bg-[#f38c00] py-4 text-body-lg font-semibold text-white transition hover:bg-[#dd7f00] disabled:opacity-60 sm:w-[260px]"><span x-show="!holding">{{ cms('cinemamovie.book_label') }}</span><span x-show="holding" x-cloak>Reserving room…</span></button>
                </div>

                <a href="{{ route('cinema') }}" wire:navigate class="text-body font-medium text-white/60 transition hover:text-white">&larr; Back to all movies</a>
            </x-layouts.container>
        </section>

        @include('partials.cinema-popup')

    </div>

    @if (! empty($paystackKey))
        @push('scripts')
            <script src="https://js.paystack.co/v1/inline.js"></script>
        @endpush
    @endif
</x-layouts.web>
