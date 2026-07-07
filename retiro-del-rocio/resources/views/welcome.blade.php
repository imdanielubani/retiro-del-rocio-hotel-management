<x-layouts.web title="Retiro Del Rocio — Luxury Hotel & Retreat in Jos"
    description="Experience stillness at Retiro Del Rocio, a luxury retreat in Jos, Plateau State. Book rooms and apartments, wellness and spa experiences, fine dining, and curated escapes across Jos City.">
    @php
        $rooms = \App\Models\Room::published()->ordered()->get();
        $roomTypes = $rooms->pluck('type')->filter()->unique()->values();

        $values = [
            ['title' => 'WELLNESS', 'text' => 'Nurturing mind, body and soul.'],
            ['title' => 'PURENESS', 'text' => "Inspired by nature's purest elements."],
            ['title' => 'TRANQUILITY', 'text' => 'A sanctuary for deep relaxation.'],
            ['title' => 'LUXURY', 'text' => 'Timeless experiences crafted with care.'],
            ['title' => 'HARMONY', 'text' => 'Balance, flow and inner peace.'],
        ];

        $offersHeading = cms('home.offers_heading');
    @endphp

    {{-- ============================ HERO + SEARCH ============================ --}}
    <section class="w-full">
        {{-- Full-width hero image (Figma 85:143 — spans the viewport edge to edge) --}}
        <div class="w-full overflow-hidden">
            <x-img src="{{ cms_image('home.hero_image') }}" alt="Retiro Del Rocio" sizes="100vw"
                   loading="eager" fetchpriority="high"
                   class="h-[380px] w-full object-cover sm:h-[520px] lg:h-[660px]" />
        </div>

        {{-- Search panel (#d9d9d9) overlapping the hero bottom, inset to the container — Figma node 92:112 --}}
        <x-layouts.container>
            <div class="relative z-10 mx-auto -mt-[100px] w-full rounded-[19px] bg-[#d9d9d9] px-4 py-5 shadow-2xl sm:-mt-[120px] sm:px-6 lg:-mt-[130px] lg:px-[26px] lg:py-[20px]"
                 x-data="{ today: new Date().toISOString().split('T')[0], checkIn: '', checkOut: '', roomSlug: '', roomsBase: '{{ url('rooms-apartment') }}', listUrl: '{{ route('rooms') }}' }">
                    {{-- Category tabs --}}
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-1 sm:gap-x-10 lg:gap-x-12">
                        <div class="flex flex-col items-start gap-2">
                            <span class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#ba6d04] sm:text-body-lg">
                                <img loading="lazy" src="{{ asset('images/fluent_bed-24-regular.png') }}" alt="" class="icon-md object-contain [filter:brightness(0)_saturate(100%)_invert(48%)_sepia(72%)_saturate(1100%)_hue-rotate(2deg)]">
                                Rooms &amp; Apartment
                            </span>
                            <span class="h-[3px] w-[207px] max-w-full rounded-full bg-[#ba6d04]"></span>
                        </div>
                        <span class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#6c6c6c] sm:text-body-lg">
                            <img loading="lazy" src="{{ asset('images/Airport.png') }}" alt="" class="icon-lg object-contain [filter:brightness(0)_opacity(45%)]">
                            Vehicle Pickup
                        </span>
                        <a href="{{ route('experience') }}" wire:navigate class="flex items-center gap-2 text-body font-semibold tracking-tight text-[#6c6c6c] transition hover:text-[#ba6d04] sm:text-body-lg">
                            <img loading="lazy" src="{{ asset('images/travel.png') }}" alt="" class="icon-lg object-contain [filter:brightness(0)_opacity(45%)]">
                            Experience
                        </a>
                    </div>
                    <hr class="my-4 border-black/10">

                    {{-- Functional search → goes straight to the chosen room's detail page
                         (carrying guests + dates so the guest doesn't re-enter them); if no
                         room is picked it falls back to the full rooms listing.
                         Wraps to a 2/3-col grid on small screens & laptops; single row from xl. --}}
                    <form method="GET" :action="roomSlug ? roomsBase + '/' + roomSlug : listUrl"
                          class="grid grid-cols-1 gap-[9px] sm:grid-cols-2 md:grid-cols-3 xl:flex xl:flex-nowrap xl:items-stretch">
                        {{-- Room (available rooms, not categories) --}}
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
                        {{-- Amenities & Services --}}
                        <!-- <div class="flex min-w-0 flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-white px-4 py-[11px] xl:min-w-0 xl:flex-[1.2]">
                            <p class="text-body-sm font-medium tracking-tight text-[#3c3c3c]">Amenities &amp; Services</p>
                            <div class="flex items-center gap-1.5">
                                <select name="amenity" class="w-full min-w-0 cursor-pointer truncate appearance-none bg-transparent text-body font-semibold tracking-tight text-[#5a5a5a] focus:outline-none">
                                    <option value="">Select</option>
                                    @foreach (['Fitness Lounge', 'Wifi', 'Pool', 'Restaurant', 'Parking', 'Complimentary Breakfast'] as $amenity)
                                        <option value="{{ $amenity }}">{{ $amenity }}</option>
                                    @endforeach
                                </select>
                                <img loading="lazy" src="{{ asset('images/keyboard_arrow_down.png') }}" alt="" class="pointer-events-none icon-sm shrink-0 object-contain">
                            </div>
                        </div> -->
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

    {{-- ====================== WHERE STILLNESS FINDS YOU ====================== --}}
    <section class="relative w-full overflow-hidden py-20 lg:pb-[120px]">
        <x-layouts.container>
            {{-- Heading with the del.png wordmark sitting behind + below it (like the design) --}}
            <div class="relative">
                {{-- Watermark: flush left, anchored just under the heading's first line, extending down --}}
                <x-img src="{{ asset('images/del.png') }}" alt="" sizes="100vw"
                     loading="lazy" aria-hidden="true"
                     class="pointer-events-none absolute left-0 top-[78px] z-0 hidden w-full max-w-none select-none opacity-[10] lg:block" />

                <div class="relative z-10 grid grid-cols-1 items-start gap-6 px-4 sm:px-8 lg:grid-cols-2 lg:gap-16 lg:px-[60px]">
                    <h2 class="text-4xl font-medium leading-tight tracking-tight text-white sm:text-6xl lg:text-[72px] lg:leading-[74px]">
                        {{ cms('home.stillness_title') }}
                    </h2>
                    <p class="text-lg leading-relaxed tracking-tight text-white/90 lg:pt-3 lg:text-body-lg">
                        {{ cms('home.stillness_text') }}
                    </p>
                </div>
            </div>

            {{-- Mobile: del.png wordmark shown below the text in a clear band (not behind text) --}}
            <x-img src="{{ asset('images/del.png') }}" alt="" sizes="100vw"
                 loading="lazy" aria-hidden="true"
                 class="pointer-events-none mt-10 h-[62px] w-full max-w-none select-none object-contain object-left opacity-90 lg:hidden" />
        </x-layouts.container>

    </section>

    
        {{-- Values strip (roll.jpg) — full-bleed, full viewport width --}}
        <div class="no-scrollbar mt-10 w-full overflow-x-auto lg:mt-14">
            <x-img src="{{ asset('images/roll.jpg') }}"
                 alt="Wellness · Pureness · Tranquility · Luxury · Harmony" sizes="100vw"
                 loading="lazy" class="h-auto w-full min-w-[760px]" />
        </div>

    {{-- ===== TEAL BACKDROP (Rectangle 611.jpg) behind offers #1 + destination ===== --}}
    <div class="relative bg-no-repeat"
         style="background-image: url('{{ str_replace(' ', '%20', asset('images/Rectangle 611.jpg')) }}'); background-size: 100% 100%;">

    {{-- ===================== EXCLUSIVE OFFERS (carousel) ====================== --}}
    <x-layouts.offers :rooms="$rooms" :heading="$offersHeading" />

    {{-- ===================== MORE THAN A DESTINATION ======================= --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container>
            <div class="relative overflow-hidden rounded-[20px]">
                <x-img src="{{ cms_image('home.destination_image') }}" alt="More than a destination"
                       sizes="(min-width:1280px) 1280px, 100vw" loading="lazy" decoding="async"
                       class="h-[440px] w-full object-cover sm:h-[600px] lg:h-[780px]" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent"></div>

                {{-- Heading (left) + paragraph (right), aligned to the bottom --}}
                <div class="absolute inset-x-0 bottom-0 flex flex-col gap-6 p-6 sm:p-10 lg:flex-row lg:items-end lg:gap-12 lg:p-16">
                    <h2 class="text-4xl font-medium leading-none tracking-tight text-white sm:text-6xl lg:w-[640px] lg:shrink-0 lg:text-[92px] lg:leading-[90px]">
                        {{ cms('home.destination_title') }}
                    </h2>
                    <p class="text-base leading-relaxed tracking-tight text-white/90 lg:flex-1 lg:pb-3 lg:text-body-lg">
                        {{ cms('home.destination_text') }}
                    </p>
                </div>
            </div>

        </x-layouts.container>
    </section>
    </div>
    {{-- /teal backdrop (Rectangle 611) --}}

    {{-- ========================= BECOME A MEMBER =========================== --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container>
            <div class="grid grid-cols-1 overflow-hidden rounded-[20px] lg:grid-cols-[57%_43%]">
                <x-img src="{{ cms_image('home.member_image') }}" alt="" sizes="(min-width:1024px) 57vw, 100vw"
                       loading="lazy" decoding="async"
                       class="h-[260px] w-full object-cover lg:h-full lg:min-h-[508px]" />
                <div class="flex flex-col justify-center gap-[27px] bg-[#e8e6e1] px-8 py-12 lg:px-[51px] lg:py-[80px]">
                    <div class="flex flex-col gap-[12px] text-[#343a40]">
                        <h2 class="text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-h1 lg:leading-[52px]">
                            {{ cms('home.member_title') }}
                        </h2>
                        <p class="text-base leading-snug lg:text-body-lg">
                            {{ cms('home.member_text') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ cms('home.member_cta_url') ?: '#' }}"
                           class="flex items-center justify-center rounded-[6px] bg-[#ba6d04] px-6 py-4 text-body-lg font-semibold text-white transition hover:bg-[#a35f03]">
                            {{ cms('home.member_cta_label') }}
                        </a>
                        <a href="{{ cms('home.member_link_url') ?: '#' }}"
                           class="flex items-center justify-center gap-3 rounded-[6px] px-6 py-4 text-body-lg font-semibold text-[#343a40] transition hover:text-[#ba6d04]">
                            {{ cms('home.member_link_label') }}
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><path d="m10 8 4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </x-layouts.container>
    </section>

    {{-- ================== EXCLUSIVE OFFERS (carousel) #2 ===================== --}}
    <x-layouts.offers :rooms="$rooms" :heading="$offersHeading" />

    {{-- ===================== BEYOND THE STAY / JOS ======================== --}}
    <section class="w-full py-16 lg:py-24">
        <x-layouts.container class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- top-left landscape --}}
            <x-img src="{{ cms_image('home.jos_image_1') }}" alt="Jos City" sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[240px] w-full rounded-[18px] object-cover lg:h-[300px]" />
            {{-- top-right text --}}
            <div class="flex flex-col justify-center gap-[24px]">
                <h2 class="text-4xl font-medium leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[60px]">
                    {{ cms('home.jos_title') }}
                </h2>
                <p class="text-base leading-relaxed tracking-tight text-white/90 lg:text-body-lg">
                    {{ cms('home.jos_text') }}
                </p>
            </div>
            {{-- bottom-left ruins --}}
            <x-img src="{{ cms_image('home.jos_image_2') }}" alt="Jos City" sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[240px] w-full rounded-[18px] object-cover lg:h-[320px]" />
            {{-- bottom-right forest --}}
            <x-img src="{{ cms_image('home.jos_image_3') }}" alt="Jos City" sizes="(min-width:640px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[240px] w-full rounded-[18px] object-cover lg:h-[320px]" />
        </x-layouts.container>
    </section>

    {{-- ============ WELLNESS LIFESTYLE + TRAIN. RECOVER. RECHARGE. ========= --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Left: wellness lifestyle tall image card --}}
            <div class="relative min-h-[480px] overflow-hidden rounded-[20px] lg:min-h-[760px]">
                <x-img src="{{ cms_image('home.wellness_image') }}" alt="Wellness lifestyle"
                       sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                       class="absolute inset-0 h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 flex flex-col gap-[29px] p-8 lg:p-[60px]">
                    <h2 class="max-w-[420px] text-3xl font-medium leading-tight tracking-tight text-white sm:text-4xl lg:text-display lg:leading-[58px]">
                        {{ cms('home.wellness_title') }}
                    </h2>
                    <a href="{{ cms('home.wellness_cta_url') ?: '#' }}"
                       class="flex h-[64px] w-[220px] items-center justify-center rounded-[10px] bg-[#ba6d04] text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03]">
                        {{ cms('home.wellness_cta_label') }}
                    </a>
                </div>
            </div>

            {{-- Right: train/recover text + gym image --}}
            <div class="flex flex-col gap-8">
                <div class="flex flex-col gap-[24px]">
                    <h2 class="text-3xl font-medium leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[60px]">
                        {{ cms('home.train_title') }}
                    </h2>
                    <p class="text-base leading-relaxed tracking-tight text-white/90 lg:text-body-lg">
                        {{ cms('home.train_text') }}
                    </p>
                </div>
                <x-img src="{{ cms_image('home.train_image') }}" alt="Wellness"
                       sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                       class="h-[300px] w-full flex-1 rounded-[18px] object-cover lg:h-auto lg:min-h-[480px]" />
            </div>
        </x-layouts.container>
    </section>
</x-layouts.web>
