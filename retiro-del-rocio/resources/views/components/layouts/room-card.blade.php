@props([
    'room' => null,
    // Fallbacks when no Room model is passed.
    'image' => '',
    'name' => "Pandora's Suite",
    'price' => '₦350,000',
    'href' => null,
])

@php
    if ($room) {
        $imageUrl = $room->featuredUrl();
        $name = $room->name;
        $price = $room->priceLabel();
        $href = route('rooms.show', $room);
        $sqft = $room->sqft;
        $beds = $room->beds;
        $guests = $room->guests;
        $bath = $room->bathrooms;
    } else {
        $imageUrl = $image ? str_replace(' ', '%20', asset('images/'.$image)) : null;
        $href = $href ?? '#';
        $sqft = 45; $beds = 2; $guests = 2; $bath = 1;
    }
@endphp

<a href="{{ $href }}" wire:navigate class="group relative block overflow-hidden rounded-2xl bg-[#1e1e1e]">
    @if ($imageUrl)
        <x-img src="{{ $imageUrl }}" alt="{{ $name }}"
               sizes="(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw" loading="lazy" decoding="async"
               class="h-[320px] w-full object-cover transition duration-500 group-hover:scale-105 sm:h-[400px] lg:h-[500px]" />
    @else
        <div class="h-[320px] w-full sm:h-[400px] lg:h-[500px]"></div>
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent"></div>

    <div class="absolute inset-x-0 bottom-0 flex flex-col gap-4 p-6 lg:p-9">
        {{-- Title + price --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-2xl font-semibold tracking-tight text-white lg:text-h2">{{ $name }}</p>
            <p class="flex items-baseline gap-1 text-white">
                <span class="text-2xl font-bold tracking-tight lg:text-h3">{{ $price }}</span>
                <span class="text-base font-semibold text-white/60 lg:text-body-lg">/ night</span>
            </p>
        </div>

        {{-- Amenities --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-body-sm font-medium tracking-tight text-white lg:text-body-lg">
            <span class="flex items-center gap-1.5">
                <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8V3h5M21 8V3h-5M3 16v5h5M21 16v5h-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $sqft }} Sq ft
            </span>
            <span class="flex items-center gap-1.5">
                <img loading="lazy" src="{{ asset('images/fluent_bed-24-regular.png') }}" alt="" class="icon-sm shrink-0 object-contain [filter:brightness(0)_invert(1)]">
                {{ $beds }} {{ \Illuminate\Support\Str::plural('Bed', $beds) }}
            </span>
            <span class="flex items-center gap-1.5">
                <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4a3 3 0 0 1 0 6M21 20c0-2.5-1.5-4.6-3.6-5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $guests }} Guest
            </span>
            <span class="flex items-center gap-1.5">
                <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2M3 12h18v2a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-2zM6 19l-1 2M18 19l1 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ $bath }} {{ \Illuminate\Support\Str::plural('Bathroom', $bath) }}
            </span>
        </div>
    </div>
</a>
