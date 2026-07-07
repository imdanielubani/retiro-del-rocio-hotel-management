<x-layouts.web title="Cinema — Retiro Del Rocio"
    description="Book tickets to the latest blockbusters at Retiro Del Rocio cinema. Now showing, coming soon, premium seats and snacks in Jos.">

    @php
        $featured = \App\Models\Movie::active()->where('is_featured', true)->ordered()->get();
        if ($featured->isEmpty()) {
            $featured = \App\Models\Movie::active()->nowShowing()->ordered()->take(3)->get();
        }
        $nowShowing = \App\Models\Movie::active()->nowShowing()->ordered()->get();
        $top10 = $nowShowing->take(10);
        $comingSoon = \App\Models\Movie::active()->comingSoon()->ordered()->get();

        $platforms = collect(cms_array('cinema.platforms'))
            ->filter(fn ($p) => ! empty($p['image']))
            ->values();
    @endphp

    <div class="bg-[#0e0e10]">

        {{-- ============================ NETFLIX-STYLE HERO ============================ --}}
        @if ($featured->isNotEmpty())
            <section class="relative w-full overflow-hidden"
                     x-data="{ i: 0, n: {{ $featured->count() }}, timer: null,
                               start() { this.timer = setInterval(() => this.next(), 7000); },
                               go(k) { this.i = k; clearInterval(this.timer); this.start(); },
                               next() { this.i = (this.i + 1) % this.n; },
                               prev() { this.i = (this.i - 1 + this.n) % this.n; } }"
                     x-init="start()">
                <div class="relative h-[520px] w-full sm:h-[600px] lg:h-[680px]">
                    @foreach ($featured as $m)
                        <div x-show="i === {{ $loop->index }}" x-transition.opacity.duration.700ms class="absolute inset-0">
                            <x-img src="{{ $m->backdropUrl() }}" alt="{{ $m->title }}" sizes="100vw"
                                   loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                                   class="h-full w-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/60 to-black/20"></div>
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0e0e10] via-transparent to-transparent"></div>
                            <x-layouts.container class="absolute inset-0 flex items-center">
                                <div class="flex max-w-[640px] flex-col gap-5">
                                    <div class="flex items-center gap-3 text-label text-white/80">
                                        <span class="rounded bg-[#f38c00] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">{{ $m->classificationLabel() }}</span>
                                        @if ($m->rating)<span class="rounded border border-white/30 px-2 py-0.5 text-[11px] font-semibold">{{ $m->rating }}</span>@endif
                                        <span>{{ $m->genre }}</span>
                                        @if ($m->duration)<span>· {{ $m->duration }}</span>@endif
                                    </div>
                                    <h1 class="text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[1.05]">{{ $m->title }}</h1>
                                    <p class="line-clamp-3 max-w-[560px] text-body leading-relaxed text-white/80 lg:text-body-lg">{{ $m->synopsis }}</p>
                                    <div class="flex flex-wrap items-center gap-4">
                                        <a href="{{ route('cinema.movie', $m) }}" wire:navigate
                                           class="inline-flex items-center gap-2.5 rounded-[10px] bg-[#f38c00] px-8 py-3.5 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#dd7f00]">
                                            Book Movie Ticket
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                        </a>
                                        @if ($m->trailer_url)
                                            <a href="{{ $m->trailer_url }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-2.5 rounded-[10px] border border-white/60 px-8 py-3.5 text-body-lg font-medium text-white transition hover:bg-white/10">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                                Watch Trailer
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </x-layouts.container>
                        </div>
                    @endforeach

                    @if ($featured->count() > 1)
                        {{-- Dots --}}
                        <div class="absolute bottom-6 left-1/2 z-10 flex -translate-x-1/2 gap-2">
                            @foreach ($featured as $m)
                                <button type="button" @click="go({{ $loop->index }})" :class="i === {{ $loop->index }} ? 'w-7 bg-[#f38c00]' : 'w-2.5 bg-white/40'" class="h-2.5 rounded-full transition-all"></button>
                            @endforeach
                        </div>
                        {{-- Arrows --}}
                        <button type="button" @click="prev()" class="absolute left-4 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition hover:bg-black/70 lg:flex"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        <button type="button" @click="next()" class="absolute right-4 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition hover:bg-black/70 lg:flex"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    @endif
                </div>
            </section>
        @endif

        {{-- ============================ OFFER TICKER ============================ --}}
        <div class="w-full bg-[#1b1b18] py-3">
            <x-layouts.container class="flex flex-wrap items-center justify-center gap-2 text-center">
                <span class="text-body font-semibold text-white">{{ cms('cinema.offer_text') }}</span>
                <span class="text-label text-[#f38c00]">{{ cms('cinema.offer_terms') }}</span>
            </x-layouts.container>
        </div>

        {{-- ============================ NOW SHOWING ============================ --}}
        @if ($nowShowing->isNotEmpty())
            <section class="w-full py-16 lg:py-20">
                <x-layouts.container class="flex flex-col gap-10">
                    <div class="flex flex-col items-center gap-3 text-center">
                        <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('cinema.nowshowing_title') }}</h2>
                        <p class="max-w-[820px] text-body leading-relaxed text-white/65 lg:text-body-lg">{{ cms('cinema.nowshowing_text') }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($nowShowing->take(3) as $m)
                            <div class="group relative overflow-hidden rounded-[18px]">
                                <x-img src="{{ $m->posterUrl() }}" alt="{{ $m->title }}" sizes="(min-width:1024px) 33vw, 100vw"
                                       loading="lazy" decoding="async" class="h-[460px] w-full object-cover lg:h-[560px]" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
                                <div class="absolute inset-x-0 bottom-0 flex flex-col items-center gap-3 p-6 text-center">
                                    <p class="text-xl font-semibold text-white">{{ $m->title }}</p>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('cinema.movie', $m) }}" wire:navigate class="rounded-[10px] bg-[#f38c00] px-6 py-2.5 text-body font-semibold text-white transition hover:bg-[#dd7f00]">Get Ticket</a>
                                        @if ($m->trailer_url)
                                            <a href="{{ $m->trailer_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-body font-medium text-white/85 hover:text-white">
                                                <svg class="size-4 text-[#f38c00]" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Watch Trailer
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-layouts.container>
            </section>
        @endif

        {{-- ============================ PLATFORM LOGOS (MARQUEE) ============================ --}}
        @if ($platforms->isNotEmpty())
            @php
                // Repeat the logos until one strip is wide enough to overflow any
                // screen, so the marquee always stays full and loops with no gap.
                $strip = collect();
                while ($strip->count() < 16) {
                    $strip = $strip->concat($platforms);
                }
            @endphp
            <div class="group w-full overflow-hidden border-y border-white/10 bg-[#121214] py-8">
                <div class="marquee-track flex w-max items-center group-hover:[animation-play-state:paused]">
                    {{-- Two identical strips make the scroll loop seamlessly (translate -50%). --}}
                    @for ($copy = 0; $copy < 2; $copy++)
                        @foreach ($strip as $p)
                            <img src="{{ \App\Models\SiteContent::imageUrl($p['image']) }}" alt="{{ $p['name'] ?? '' }}"
                                 class="h-7 w-auto shrink-0 object-contain pr-14 lg:h-9 lg:pr-20" loading="lazy" decoding="async"
                                 aria-hidden="{{ $copy === 1 ? 'true' : 'false' }}">
                        @endforeach
                    @endfor
                </div>
            </div>
        @endif

        {{-- ============================ TOP 10 THIS WEEK ============================ --}}
        @if ($top10->isNotEmpty())
            <section class="w-full py-16 lg:py-20">
                <x-layouts.container>
                    <div class="mb-8 flex items-end justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <h2 class="text-2xl font-semibold tracking-tight text-white lg:text-h2">{{ cms('cinema.top10_title') }}</h2>
                            <p class="text-body text-white/55">{{ cms('cinema.top10_text') }}</p>
                        </div>
                        <a href="{{ route('cinema.movies') }}" wire:navigate
                           class="inline-flex shrink-0 items-center gap-2 rounded-[10px] border border-white/25 px-5 py-2.5 text-body font-medium text-white transition hover:bg-white/10">
                            View All
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                    @include('partials.cinema-carousel', ['movies' => $top10])
                </x-layouts.container>
            </section>
        @endif

        {{-- ============================ WEEKEND BAND ============================ --}}
        <section class="w-full py-6 lg:py-10">
            <x-layouts.container>
                @php $weekendUrl = cms('cinema.weekend_url') ?: route('cinema.movies'); @endphp
                <div class="relative isolate overflow-hidden rounded-[16px] sm:rounded-[22px]">
                    {{-- Background image fills the band (which grows to fit the content height). --}}
                    <x-img src="{{ cms_image('cinema.weekend_image') }}" alt="" sizes="100vw" loading="lazy" decoding="async"
                           class="absolute inset-0 -z-10 h-full w-full object-cover" />
                    {{-- Stronger overlay across the whole width on mobile; left-anchored from sm up. --}}
                    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/85 via-black/60 to-black/35 sm:from-black/80 sm:via-black/40 sm:to-transparent"></div>
                    <div class="flex min-h-[340px] items-center sm:min-h-[400px] lg:min-h-[460px]">
                        <div class="flex w-full max-w-[650px] flex-col gap-4 px-5 py-10 sm:gap-5 sm:px-8 sm:py-12 lg:px-14">
                            <h2 class="text-2xl font-semibold leading-tight tracking-tight text-white sm:text-3xl lg:text-h1">{{ cms('cinema.weekend_title') }}</h2>
                            <p class="text-body-sm leading-relaxed text-white/80 sm:text-body lg:text-body-lg">{{ cms('cinema.weekend_text') }}</p>
                            <div>
                                <a href="{{ $weekendUrl }}" @if (\Illuminate\Support\Str::startsWith($weekendUrl, '/')) wire:navigate @endif
                                   class="inline-flex w-full items-center justify-center gap-2.5 rounded-[10px] bg-[#f38c00] px-6 py-3.5 text-body font-semibold text-white transition hover:bg-[#dd7f00] sm:w-auto sm:px-8 sm:text-body-lg">
                                    {{ cms('cinema.weekend_button') }}
                                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </x-layouts.container>
        </section>

        {{-- ============================ COMING SOON ============================ --}}
        @if ($comingSoon->isNotEmpty())
            <section id="top-movies" class="w-full py-16 lg:py-20">
                <x-layouts.container>
                    <div class="mb-8 flex items-end justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <h2 class="text-2xl font-semibold tracking-tight text-white lg:text-h2">{{ cms('cinema.comingsoon_title') }}</h2>
                            <p class="text-body text-white/55">{{ cms('cinema.comingsoon_text') }}</p>
                        </div>
                        <a href="{{ route('cinema.movies', ['type' => 'coming_soon']) }}" wire:navigate
                           class="inline-flex shrink-0 items-center gap-2 rounded-[10px] border border-white/25 px-5 py-2.5 text-body font-medium text-white transition hover:bg-white/10">
                            View All
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                    @include('partials.cinema-carousel', ['movies' => $comingSoon])
                </x-layouts.container>
            </section>
        @endif

    </div>
</x-layouts.web>
