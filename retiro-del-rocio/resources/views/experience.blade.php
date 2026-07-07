<x-layouts.web title="Experience Jos — Retiro Del Rocio"
    description="Beyond your stay, explore Jos City. Discover trending destinations, calm nature escapes and adventures curated by Retiro Del Rocio.">

    @php
        $rooms = \App\Models\Room::published()->ordered()->get();
        $destinations = collect(range(1, 6))->map(fn ($i) => [
            'image' => cms_image("experience.dest_{$i}_image"),
            'title' => cms("experience.dest_{$i}_title"),
            'text' => cms("experience.dest_{$i}_text"),
        ]);
    @endphp

    {{-- ============================ HERO + SEARCH (matches homepage) ============================ --}}
    <section class="w-full">
        {{-- Full-width hero image --}}
        <div class="w-full overflow-hidden">
            <x-img src="{{ cms_image('experience.hero_image') }}" alt="Explore Jos" sizes="100vw"
                   loading="eager" fetchpriority="high"
                   class="h-[380px] w-full object-cover sm:h-[520px] lg:h-[660px]" />
        </div>

        {{-- Search panel overlapping the hero bottom (same as homepage) --}}
        <x-layouts.container>
            <div class="relative z-10 mx-auto -mt-[100px] w-full rounded-[19px] bg-[#d9d9d9] px-4 py-5 shadow-2xl sm:-mt-[120px] sm:px-6 lg:-mt-[130px] lg:px-[26px] lg:py-[20px]"
                 x-data="{ today: new Date().toISOString().split('T')[0], checkIn: '', checkOut: '', roomSlug: '', roomsBase: '{{ url('rooms-apartment') }}', listUrl: '{{ route('rooms') }}' }">
                {{-- Category tabs — Experience active; Rooms & Apartment goes home --}}
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-1 sm:gap-x-10 lg:gap-x-12">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#6c6c6c] transition hover:text-[#ba6d04] sm:text-body-lg">
                        <img loading="lazy" src="{{ asset('images/fluent_bed-24-regular.png') }}" alt="" class="icon-md object-contain [filter:brightness(0)_opacity(45%)]">
                        Rooms &amp; Apartment
                    </a>
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#6c6c6c] transition hover:text-[#ba6d04] sm:text-body-lg">
                        <img loading="lazy" src="{{ asset('images/Airport.png') }}" alt="" class="icon-lg object-contain [filter:brightness(0)_opacity(45%)]">
                        Vehicle Pickup
                    </a>
                    <div class="flex flex-col items-start gap-2">
                        <span class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#ba6d04] sm:text-body-lg">
                            <img loading="lazy" src="{{ asset('images/travel.png') }}" alt="" class="icon-lg object-contain [filter:brightness(0)_saturate(100%)_invert(48%)_sepia(72%)_saturate(1100%)_hue-rotate(2deg)]">
                            Experience
                        </span>
                        <span class="h-[3px] w-full rounded-full bg-[#ba6d04]"></span>
                    </div>
                </div>
                <hr class="my-4 border-black/10">

                {{-- Functional search → chosen room detail (carrying guests + dates), else the rooms listing. --}}
                <form method="GET" :action="roomSlug ? roomsBase + '/' + roomSlug : listUrl"
                      class="grid grid-cols-1 gap-[9px] sm:grid-cols-2 md:grid-cols-3 xl:flex xl:flex-nowrap xl:items-stretch">
                    {{-- Room --}}
                    <div class="flex min-w-0 flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-white px-4 py-[13px] xl:min-w-0 xl:flex-[1.4]">
                        <p class="text-body-sm font-medium tracking-tight text-[#3c3c3c]">Room</p>
                        <div class="flex items-center gap-1.5">
                            <select x-model="roomSlug" class="w-full min-w-0 cursor-pointer truncate appearance-none bg-transparent text-body font-semibold text-black focus:outline-none">
                                <option value="">Browse all rooms</option>
                                @foreach ($rooms as $r)
                                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                            <img loading="lazy" src="{{ asset('images/keyboard_arrow_down.png') }}" alt="" class="pointer-events-none icon-sm shrink-0 object-contain">
                        </div>
                    </div>
                    {{-- Number of Guest --}}
                    <div class="flex min-w-0 flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-white px-4 py-[14px] xl:min-w-0 xl:flex-1">
                        <p class="text-body-sm font-medium tracking-tight text-[#3c3c3c]">Number of Guest</p>
                        <div class="flex items-center gap-1.5">
                            <select name="guests" class="w-full min-w-0 cursor-pointer appearance-none bg-transparent text-body font-semibold text-black focus:outline-none">
                                @for ($n = 1; $n <= 10; $n++)
                                    <option value="{{ $n }}" @selected($n === 2)>{{ $n }}</option>
                                @endfor
                            </select>
                            <img loading="lazy" src="{{ asset('images/keyboard_arrow_down.png') }}" alt="" class="pointer-events-none icon-sm shrink-0 object-contain">
                        </div>
                    </div>
                    {{-- Check-in Date --}}
                    <div class="flex min-w-0 flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-white px-4 py-[11px] xl:min-w-0 xl:flex-1">
                        <p class="text-body-sm font-medium tracking-tight text-[#3c3c3c]">Check-in Date</p>
                        <div class="flex items-center gap-1.5">
                            <img loading="lazy" src="{{ asset('images/date.png') }}" alt="" class="icon-sm shrink-0 object-contain">
                            <input type="date" name="check_in" x-model="checkIn" :min="today"
                                   @click="$event.target.showPicker && $event.target.showPicker()"
                                   class="w-full min-w-0 cursor-pointer bg-transparent text-body-sm font-semibold text-black focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
                        </div>
                    </div>
                    {{-- Check-out Date --}}
                    <div class="flex min-w-0 flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-white px-4 py-[11px] xl:min-w-0 xl:flex-1">
                        <p class="text-body-sm font-medium tracking-tight text-[#3c3c3c]">Check-out Date</p>
                        <div class="flex items-center gap-1.5">
                            <img loading="lazy" src="{{ asset('images/date.png') }}" alt="" class="icon-sm shrink-0 object-contain">
                            <input type="date" name="check_out" x-model="checkOut" :min="checkIn || today"
                                   @click="$event.target.showPicker && $event.target.showPicker()"
                                   class="w-full min-w-0 cursor-pointer bg-transparent text-body-sm font-semibold text-black focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
                        </div>
                    </div>
                    {{-- Search --}}
                    <button type="submit"
                            class="flex min-h-[64px] shrink-0 items-center justify-center gap-[10px] rounded-[14px] bg-[#ba6d04] px-6 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03] sm:col-span-2 md:col-span-1 xl:min-w-[130px] xl:flex-1">
                        Search
                        <img loading="lazy" src="{{ asset('images/search-line.png') }}" alt="" class="icon-md object-contain [filter:brightness(0)_invert(1)]">
                    </button>
                </form>
            </div>
        </x-layouts.container>
    </section>

    {{-- =================== BEYOND YOUR STAY — EXPLORE JOS =================== --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container class="flex flex-col gap-8 lg:gap-10">
            <x-img src="{{ cms_image('experience.jos_image') }}" alt="{{ cms('experience.jos_title') }}"
                   sizes="(min-width:1280px) 1280px, 100vw" loading="lazy" decoding="async"
                   class="h-[260px] w-full rounded-[18px] object-cover sm:h-[440px] lg:h-[600px]" />
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[40%_60%] lg:items-start lg:gap-10">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight text-[#f38c00] sm:text-4xl lg:text-h1">{{ cms('experience.jos_title') }}</h2>
                <p class="text-body leading-relaxed tracking-tight text-white/70 lg:text-body-lg">{{ cms('experience.jos_text') }}</p>
            </div>
        </x-layouts.container>
    </section>

    {{-- =================== EXPERIENCE CALM & ADVENTURE =================== --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container>
            {{-- Two images side by side with the heading + text overlaid bottom-left --}}
            <div class="relative grid grid-cols-2 overflow-hidden rounded-[20px]">
                <x-img src="{{ cms_image('experience.calm_image_1') }}" alt="" sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[320px] w-full object-cover sm:h-[480px] lg:h-[600px]" />
                <x-img src="{{ cms_image('experience.calm_image_2') }}" alt="" sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[320px] w-full object-cover sm:h-[480px] lg:h-[600px]" />
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/85 to-transparent px-6 pb-12 pt-44 sm:px-10 sm:pb-14 lg:px-14 lg:pb-16">
                    <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('experience.calm_title') }}</h2>
                    <p class="mt-3 max-w-[820px] text-body leading-relaxed tracking-tight text-white/85 lg:text-body-lg">{{ cms('experience.calm_text') }}</p>
                </div>
            </div>
        </x-layouts.container>
    </section>

    {{-- =================== BECOME A MEMBER (matches homepage) =================== --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container>
            <div class="grid grid-cols-1 overflow-hidden rounded-[20px] lg:grid-cols-[57%_43%]">
                <x-img src="{{ cms_image('experience.member_image') }}" alt="" sizes="(min-width:1024px) 57vw, 100vw"
                       loading="lazy" decoding="async"
                       class="h-[260px] w-full object-cover lg:h-full lg:min-h-[508px]" />
                <div class="flex flex-col justify-center gap-[27px] bg-[#e8e6e1] px-8 py-12 lg:px-[51px] lg:py-[80px]">
                    <div class="flex flex-col gap-[12px] text-[#343a40]">
                        <h2 class="text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-h1 lg:leading-[52px]">
                            {{ cms('experience.member_title') }}
                        </h2>
                        <p class="text-base leading-snug lg:text-body-lg">
                            {{ cms('experience.member_text') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ cms('experience.member_cta_url') ?: '#' }}"
                           class="flex items-center justify-center rounded-[6px] bg-[#ba6d04] px-6 py-4 text-body-lg font-semibold text-white transition hover:bg-[#a35f03]">
                            {{ cms('experience.member_cta_label') }}
                        </a>
                        <a href="{{ cms('experience.member_link_url') ?: '#' }}"
                           class="flex items-center justify-center gap-3 rounded-[6px] px-6 py-4 text-body-lg font-semibold text-[#343a40] transition hover:text-[#ba6d04]">
                            {{ cms('experience.member_link_label') }}
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><path d="m10 8 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </x-layouts.container>
    </section>

    {{-- =================== TRENDING DESTINATION =================== --}}
    <section class="w-full py-12 pb-20 lg:py-16 lg:pb-28">
        <x-layouts.container class="flex flex-col gap-10 lg:gap-14">
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[40%_60%] lg:items-center lg:gap-10">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight text-[#f38c00] sm:text-4xl lg:text-h1">{{ cms('experience.trending_title') }}</h2>
                <p class="text-body leading-relaxed tracking-tight text-white/70 lg:text-body-lg">{{ cms('experience.trending_text') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:gap-x-12 lg:gap-y-16">
                @foreach ($destinations as $d)
                    <div class="flex flex-col gap-5">
                        <x-img src="{{ $d['image'] }}" alt="{{ $d['title'] }}"
                               sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async"
                               class="h-[240px] w-full rounded-[18px] object-cover sm:h-[360px] lg:h-[420px]" />
                        <h3 class="text-2xl font-semibold tracking-tight text-white lg:text-h3">{{ $d['title'] }}</h3>
                        <p class="text-body leading-relaxed tracking-tight text-white/70 lg:text-body-lg">{{ $d['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-layouts.container>
    </section>

</x-layouts.web>
