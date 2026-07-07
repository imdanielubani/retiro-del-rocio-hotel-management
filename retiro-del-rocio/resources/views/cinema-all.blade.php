<x-layouts.web title="All Movies — Retiro Del Rocio"
    description="Browse all movies now showing and coming soon at Retiro Del Rocio cinema. Filter by category to find your next watch.">

    @php
        // Preselect the classification tab from ?type= (used by the "View All" links).
        $type = in_array(request('type'), ['now_showing', 'coming_soon'], true) ? request('type') : 'all';

        // Unique genre tokens across all movies (genre stored as "Action, Sci-Fi").
        $genres = $movies
            ->flatMap(fn ($m) => collect(explode(',', (string) $m->genre))->map(fn ($g) => trim($g)))
            ->filter()->unique()->sort()->values();
    @endphp

    <div class="min-h-screen bg-[#0e0e10]"
         x-data="{
            cls: @js($type),
            genre: 'all',
            visible(el) {
                const c = el.dataset.cls;
                const g = (el.dataset.genres || '').split('|').filter(Boolean);
                return (this.cls === 'all' || c === this.cls) && (this.genre === 'all' || g.includes(this.genre));
            },
            get shown() { return Array.from(this.$root.querySelectorAll('[data-movie-card]')).filter((e) => this.visible(e)).length; }
         }">

        <x-layouts.container class="flex flex-col gap-8 py-12 lg:py-16">
            {{-- Header --}}
            <div class="flex flex-col gap-3">
                <a href="{{ route('cinema') }}" wire:navigate
                   class="inline-flex w-fit items-center gap-2 text-body font-medium text-white/60 transition hover:text-white">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    Back to Cinema
                </a>
                <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-h1">All Movies</h1>
                <p class="max-w-[680px] text-body leading-relaxed text-white/60 lg:text-body-lg">Browse everything now showing and coming soon — filter by category to find your next watch.</p>
            </div>

            {{-- Filters --}}
            <div class="flex flex-col gap-4 border-y border-white/10 py-6">
                {{-- Classification tabs --}}
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (['all' => 'All Movies', 'now_showing' => 'Now Showing', 'coming_soon' => 'Coming Soon'] as $key => $label)
                        <button type="button" @click="cls = @js($key)"
                                :class="cls === @js($key) ? 'bg-[#f38c00] text-white' : 'border border-white/20 text-white/70 hover:bg-white/10'"
                                class="rounded-full px-5 py-2 text-body font-semibold transition">{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Genre chips --}}
                @if ($genres->isNotEmpty())
                    <div class="flex flex-col gap-2">
                        <p class="text-label uppercase tracking-[1.5px] text-white/40">Category</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="genre = 'all'"
                                    :class="genre === 'all' ? 'bg-white text-[#0e0e10]' : 'border border-white/20 text-white/70 hover:bg-white/10'"
                                    class="rounded-full px-4 py-1.5 text-label font-semibold transition">All Genres</button>
                            @foreach ($genres as $g)
                                <button type="button" @click="genre = @js($g)"
                                        :class="genre === @js($g) ? 'bg-white text-[#0e0e10]' : 'border border-white/20 text-white/70 hover:bg-white/10'"
                                        class="rounded-full px-4 py-1.5 text-label font-medium transition">{{ $g }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Movie grid --}}
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($movies as $m)
                    @php
                        $gtokens = collect(explode(',', (string) $m->genre))->map(fn ($g) => trim($g))->filter()->implode('|');
                    @endphp
                    <div data-movie-card data-cls="{{ $m->classification }}" data-genres="{{ $gtokens }}"
                         x-show="visible($el)" x-cloak class="flex flex-col gap-3">
                        <a href="{{ route('cinema.movie', $m) }}" wire:navigate class="group relative block overflow-hidden rounded-[14px]">
                            <x-img src="{{ $m->posterUrl() }}" alt="{{ $m->title }}" sizes="(min-width:1280px) 18vw, (min-width:640px) 30vw, 45vw"
                                   loading="lazy" decoding="async"
                                   class="aspect-[2/3] w-full object-cover transition duration-300 group-hover:scale-[1.04]" />
                            @if ($m->rating)
                                <span class="absolute left-3 top-3 rounded bg-black/70 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $m->rating }}</span>
                            @endif
                            @if ($m->classification === 'coming_soon')
                                <span class="absolute right-3 top-3 rounded-full bg-[#7c3aed] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.4px] text-white">Coming Soon</span>
                            @endif
                        </a>
                        <div class="flex flex-col gap-1">
                            <p class="truncate text-body font-semibold text-white">{{ $m->title }}</p>
                            <div class="flex items-center justify-between text-label text-white/55">
                                <span class="truncate">{{ $m->genre }}</span>
                                @if ($m->duration)
                                    <span class="flex shrink-0 items-center gap-1"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>{{ $m->duration }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('cinema.movie', $m) }}" wire:navigate
                           class="mt-1 rounded-[9px] bg-[#f38c00] py-2.5 text-center text-body font-semibold text-white transition hover:bg-[#dd7f00]">
                            {{ $m->classification === 'coming_soon' ? 'View Details' : 'Get Ticket' }}
                        </a>
                    </div>
                @endforeach
            </div>

            {{-- Empty state when a filter matches nothing --}}
            <div x-show="shown === 0" x-cloak class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-white/15 py-16 text-center">
                <p class="text-body-lg font-semibold text-white">No movies in this category</p>
                <p class="text-body text-white/55">Try a different filter.</p>
            </div>
        </x-layouts.container>
    </div>
</x-layouts.web>
