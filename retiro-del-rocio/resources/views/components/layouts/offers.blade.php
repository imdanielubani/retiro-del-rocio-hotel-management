@props([
    'rooms' => [],
    'heading' => 'Explore our exclusive offers',
])

<section class="w-full py-16 lg:py-24"
         x-data="{ scrollByCard(dir) { const card = $refs.track.querySelector('[data-card]'); const step = card ? card.offsetWidth + 14 : 700; $refs.track.scrollBy({ left: dir * step, behavior: 'smooth' }); } }">
    <x-layouts.container class="flex flex-col items-center gap-[57px]">
        <h2 class="text-center text-2xl tracking-tight text-white sm:text-3xl lg:text-h2">{{ $heading }}</h2>

        {{-- Carousel --}}
        <div class="relative w-full">
            <div x-ref="track"
                 class="no-scrollbar flex w-full snap-x snap-mandatory gap-[14px] overflow-x-auto scroll-smooth">
                @foreach ($rooms as $room)
                    @php($isModel = $room instanceof \App\Models\Room)
                    @php($cardImage = $isModel ? $room->featuredUrl() : str_replace(' ', '%20', asset('images/'.$room['image'])))
                    @php($cardName = $isModel ? $room->name : $room['name'])
                    @php($cardPrice = $isModel ? $room->priceLabel() : $room['price'])
                    @php($cardHref = $isModel ? route('rooms.show', $room) : '#')
                    <a href="{{ $cardHref }}" @if ($isModel) wire:navigate @endif data-card
                       class="group relative block w-[686px] max-w-[88%] shrink-0 snap-start overflow-hidden rounded-2xl bg-[#1e1e1e]">
                        <x-img src="{{ $cardImage }}" alt="{{ $cardName }}"
                               sizes="(min-width:768px) 686px, 88vw" loading="lazy" decoding="async"
                               class="h-[420px] w-full object-cover transition duration-500 group-hover:scale-105 lg:h-[541px]" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent"></div>

                        <div class="absolute inset-x-0 bottom-0 flex flex-col gap-4 p-6 lg:p-9">
                            {{-- Title + price --}}
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-2xl font-semibold tracking-tight text-white lg:text-h2">{{ $cardName }}</p>
                                <p class="flex items-baseline gap-1 text-white">
                                    <span class="text-2xl font-bold tracking-tight lg:text-h3">{{ $cardPrice }}</span>
                                    <span class="text-base font-semibold text-white/60 lg:text-body-lg">/ night</span>
                                </p>
                            </div>

                            {{-- Amenities --}}
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-body-sm font-medium tracking-tight text-white lg:text-body-lg">
                                <span class="flex items-center gap-1.5">
                                    <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8V3h5M21 8V3h-5M3 16v5h5M21 16v5h-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    45 Sq ft
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <img loading="lazy" src="{{ asset('images/fluent_bed-24-regular.png') }}" alt="" class="icon-sm shrink-0 object-contain [filter:brightness(0)_invert(1)]">
                                    Twin Bed
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4a3 3 0 0 1 0 6M21 20c0-2.5-1.5-4.6-3.6-5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    2 Guest
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2M3 12h18v2a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-2zM6 19l-1 2M18 19l1 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    1 Bathroom
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Carousel arrows --}}
            <button type="button" @click="scrollByCard(-1)" aria-label="Previous"
                    class="absolute left-3 top-1/2 hidden icon-xl -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#222a1f] shadow-lg transition hover:bg-white lg:flex">
                <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" @click="scrollByCard(1)" aria-label="Next"
                    class="absolute right-3 top-1/2 hidden icon-xl -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-[#222a1f] shadow-lg transition hover:bg-white lg:flex">
                <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>

        <a href="#"
           class="flex h-[73px] w-[341px] max-w-full items-center justify-center gap-1.5 rounded-[10px] bg-[#ba6d04] font-poppins text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03]">
            Explore more
            <svg class="icon-md -rotate-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </x-layouts.container>
</section>
