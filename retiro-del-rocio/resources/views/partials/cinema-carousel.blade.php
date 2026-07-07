{{-- Horizontal movie card carousel. Expects $movies (Collection of Movie). --}}
<div class="relative"
     x-data="{
        canLeft: false, canRight: true,
        refresh() { const t = $refs.track; if (!t) return; this.canLeft = t.scrollLeft > 4; this.canRight = (t.scrollLeft + t.clientWidth) < (t.scrollWidth - 4); },
        slide(dir) { const t = $refs.track; const c = t.querySelector('[data-movie-card]'); const amt = c ? (c.offsetWidth + 20) : 300; t.scrollBy({ left: dir * amt, behavior: 'smooth' }); }
     }"
     x-init="$nextTick(() => refresh())">
    <button type="button" @click="slide(-1)" x-show="canLeft || canRight" :disabled="!canLeft" aria-label="Previous"
            class="absolute -left-3 top-[38%] z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#1e1e1e] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 lg:flex">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>

    <div x-ref="track" @scroll.debounce.50ms="refresh()" class="no-scrollbar flex snap-x gap-5 overflow-x-auto scroll-smooth pb-2">
        @foreach ($movies as $m)
            <div data-movie-card class="flex w-[240px] shrink-0 snap-start flex-col gap-3 sm:w-[270px]">
                <a href="{{ route('cinema.movie', $m) }}" wire:navigate class="group relative block overflow-hidden rounded-[14px]">
                    <x-img src="{{ $m->posterUrl() }}" alt="{{ $m->title }}" sizes="270px" loading="lazy" decoding="async"
                           class="h-[360px] w-full object-cover transition duration-300 group-hover:scale-[1.04] sm:h-[400px]" />
                    @if ($m->rating)
                        <span class="absolute left-3 top-3 rounded bg-black/70 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $m->rating }}</span>
                    @endif
                </a>
                <div class="flex flex-col gap-1">
                    <p class="truncate text-body font-semibold text-white">{{ $m->title }}</p>
                    <div class="flex items-center justify-between text-label text-white/55">
                        <span class="truncate">{{ $m->genre }}</span>
                        @if ($m->duration)<span class="flex shrink-0 items-center gap-1"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>{{ $m->duration }}</span>@endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('cinema.movie', $m) }}" wire:navigate class="flex-1 rounded-[9px] bg-[#f38c00] py-2.5 text-center text-body font-semibold text-white transition hover:bg-[#dd7f00]">Get Ticket</a>
                    @if ($m->trailer_url)
                        <a href="{{ $m->trailer_url }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1.5 text-label font-medium text-white/80 hover:text-white">
                            <svg class="size-4 text-[#f38c00]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>Trailer
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" @click="slide(1)" x-show="canLeft || canRight" :disabled="!canRight" aria-label="Next"
            class="absolute -right-3 top-[38%] z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#1e1e1e] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 lg:flex">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
</div>
