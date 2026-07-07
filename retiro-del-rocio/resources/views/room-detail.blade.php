<x-layouts.web :title="$room->name.' — Rooms & Apartment — Retiro Del Rocio'"
    :description="\Illuminate\Support\Str::limit(strip_tags($room->short_description ?: $room->description), 150)">

    @php
        $galleryUrls = $room->galleryUrls();
        if (empty($galleryUrls)) {
            $galleryUrls = [str_replace(' ', '%20', asset('images/image 7bg.jpg'))];
        }
        $amenities = $room->amenities ?: [];
    @endphp

    {{-- =========================== GALLERY HERO =========================== --}}
    <section x-data="{ urls: @js($galleryUrls), i: 0, prev() { this.i = (this.i - 1 + this.urls.length) % this.urls.length }, next() { this.i = (this.i + 1) % this.urls.length } }">
        {{-- Main image --}}
        <div class="relative w-full overflow-hidden">
            <img loading="eager" fetchpriority="high" :src="urls[i]" alt="{{ $room->name }}"
                 class="h-[380px] w-full object-cover sm:h-[560px] lg:h-[720px]">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/75 via-transparent to-black/20"></div>

            {{-- Breadcrumb (top-left, over hero) --}}
            <x-layouts.container class="absolute inset-x-0 top-6 lg:top-10">
                <nav class="flex items-center gap-2 text-white">
                    <a href="{{ route('rooms') }}" wire:navigate aria-label="Back"
                       class="flex icon-xl shrink-0 items-center justify-center rounded-full transition hover:bg-white/10 lg:icon-xl">
                        <svg class="icon-lg lg:icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <p class="text-base font-semibold tracking-tight sm:text-xl lg:text-title">
                        <a href="{{ route('home') }}" wire:navigate class="hover:text-[#f38c00]">Home</a>
                        <span class="text-white/80"> / </span>
                        <a href="{{ route('rooms') }}" wire:navigate class="hover:text-[#f38c00]">Room &amp; Apartment</a>
                        <span class="text-white/80"> / </span>
                        <span class="text-[#f38c00]">{{ $room->name }}</span>
                    </p>
                </nav>
            </x-layouts.container>

            {{-- Prev / Next --}}
            <button type="button" @click="prev" aria-label="Previous image"
                    class="absolute left-4 top-1/2 flex icon-xl -translate-y-1/2 items-center justify-center rounded-full border border-white/70 text-white transition hover:bg-white/15 lg:left-8 lg:size-[68px]">
                <svg class="icon-lg lg:icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" @click="next" aria-label="Next image"
                    class="absolute right-4 top-1/2 flex icon-xl -translate-y-1/2 items-center justify-center rounded-full border border-white/70 text-white transition hover:bg-white/15 lg:right-8 lg:size-[68px]">
                <svg class="icon-lg lg:icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>

            {{-- Dots --}}
            <div class="absolute inset-x-0 bottom-5 flex items-center justify-center gap-2">
                <template x-for="(u, idx) in urls" :key="idx">
                    <button type="button" @click="i = idx" aria-label="Go to image"
                            class="h-1.5 rounded-full transition-all"
                            :class="i === idx ? 'w-7 bg-[#f38c00]' : 'w-4 bg-white/50'"></button>
                </template>
            </div>
        </div>

        {{-- Thumbnail strip --}}
        <div class="no-scrollbar flex gap-[2px] overflow-x-auto bg-[#4e3a31]">
            <template x-for="(u, idx) in urls.slice(1)" :key="idx">
                <button type="button" @click="i = idx + 1" class="shrink-0">
                    <img loading="lazy" :src="u" alt="" class="h-[140px] w-[220px] object-cover transition lg:h-[235px] lg:w-[352px]"
                         :class="i === idx + 1 ? 'opacity-100 ring-2 ring-[#f38c00]' : 'opacity-90 hover:opacity-100'">
                </button>
            </template>
        </div>
    </section>

    {{-- =========================== BOOKING / SUMMARY =========================== --}}
    {{-- "backdrop" block (Figma node 85:990): dark gradient behind the title + booking area --}}
    <section x-data="{
                airportModal: false,
                vehicleModal: false,
                {{-- Pre-filled from the homepage search so the guest doesn't re-enter it. --}}
                guests: {{ (int) (request('guests') ?: 2) }},
                checkIn: @js(request('check_in', '')),
                checkOut: @js(request('check_out', '')),
                today: new Date().toISOString().split('T')[0],
                get datesValid() { return !!(this.checkIn && this.checkOut && this.checkOut > this.checkIn); },
                location: @js(collect(cms_array('pickup.locations'))->pluck('name')->first() ?: 'Airport Pickup'), {{-- pickup point (editable in CMS) --}}
                passengers: 2,
                arrivalDate: '',
                pickupTime: '',
                flightNumber: '',
                pickup: null,
                searched: false,
                searchError: '',
                doSearch() {
                    if (!this.arrivalDate || !this.pickupTime || !this.flightNumber) {
                        const numberLabel = /airport/i.test(this.location) ? 'flight number' : 'bus number';
                        this.searchError = 'Please enter your arrival date, pick-up time and ' + numberLabel + ' to see available vehicles.';
                        this.searched = false;
                        return false;
                    }
                    if (this.arrivalDate < this.today) {
                        this.searchError = 'The arrival date cannot be in the past. Please choose today or a future date.';
                        this.searched = false;
                        return false;
                    }
                    this.searchError = '';
                    this.searched = true;
                    return true;
                },
                availUrl: @js(route('rooms.availability', $room)),
                checking: false, availChecked: false, available: true, availCount: null,
                selectVehicle(v) { this.pickup = v; this.vehicleModal = false; },
                fmtDate(d) { if (!d) return ''; return new Date(d + 'T00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); },
                fmtTime(t) { if (!t) return ''; let [h, m] = t.split(':'); h = parseInt(h); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + m + ' ' + ap; },
                async checkAvail() {
                    if (!this.checkIn || !this.checkOut || this.checkOut <= this.checkIn) { this.availChecked = false; this.available = true; this.availCount = null; return; }
                    this.checking = true;
                    try {
                        const r = await fetch(this.availUrl + '?check_in=' + this.checkIn + '&check_out=' + this.checkOut, { headers: { 'Accept': 'application/json' } });
                        const d = await r.json();
                        this.available = d.available !== false;
                        this.availCount = d.count;
                        this.availChecked = !!d.ok;
                    } catch (e) { this.availChecked = false; this.available = true; }
                    this.checking = false;
                },
                init() { this.checkAvail(); this.$watch('checkIn', () => this.checkAvail()); this.$watch('checkOut', () => this.checkAvail()); },
             }"
             class="w-full bg-gradient-to-t from-[#222a1f] to-[#1e1e1e] py-12 lg:py-16">
        <x-layouts.container class="flex flex-col gap-[26px]">
            {{-- Title + price --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ $room->name }}</h1>
                <p class="flex items-baseline gap-1 text-white">
                    <span class="text-2xl font-semibold tracking-tight lg:text-h2">{{ $room->priceLabel() }}</span>
                    <span class="text-base text-white/60 lg:text-body-lg">/ night</span>
                </p>
            </div>

            {{-- Booking row (functional) --}}
            <form method="POST" action="{{ route('checkout.start') }}" class="flex flex-col gap-[3px] sm:flex-row sm:flex-wrap"
                  @submit="if (!datesValid || (availChecked && !available)) $event.preventDefault()">
                @csrf
                <input type="hidden" name="room" value="{{ $room->name }}">
                <input type="hidden" name="room_slug" value="{{ $room->slug }}">
                <input type="hidden" name="price" value="{{ $room->priceLabel() }}">
                {{-- Airport pick-up (only submitted when a vehicle is selected) --}}
                <input type="hidden" name="pickup_vehicle" :value="pickup ? pickup.name : ''">
                <input type="hidden" name="pickup_price" :value="pickup ? pickup.price : ''">
                <input type="hidden" name="location" :value="pickup ? location : ''">
                <input type="hidden" name="passengers" :value="pickup ? passengers : ''">
                <input type="hidden" name="arrival_date" :value="pickup ? arrivalDate : ''">
                <input type="hidden" name="pickup_time" :value="pickup ? pickupTime : ''">
                <input type="hidden" name="flight_number" :value="pickup ? flightNumber : ''">

                <div class="flex min-w-[240px] flex-1 flex-col justify-center rounded-[6px] border-[0.5px] border-black/20 bg-[#f6f6f6]/[0.87] px-[23px] py-[14px]">
                    <label class="text-body-sm font-medium tracking-tight text-black">Number of Guest</label>
                    <div class="flex items-center justify-between">
                        <select name="guests" x-model.number="guests"
                                class="w-full appearance-none bg-transparent text-body-lg font-bold text-black focus:outline-none">
                            @for ($n = 1; $n <= 10; $n++)
                                <option value="{{ $n }}">{{ $n }}</option>
                            @endfor
                        </select>
                        <img loading="lazy" src="{{ asset('images/keyboard_arrow_down.png') }}" alt="" class="pointer-events-none icon-md shrink-0 object-contain">
                    </div>
                </div>
                <div class="flex min-w-[200px] flex-1 flex-col justify-center rounded-[6px] border-[0.5px] border-black/20 bg-[#f6f6f6]/[0.87] px-[25px] py-[11px]">
                    <label class="text-body-sm font-medium tracking-tight text-black">Check-in Date</label>
                    <div class="flex items-center gap-[5px]">
                        <img loading="lazy" src="{{ asset('images/date.png') }}" alt="" class="icon-sm shrink-0 object-contain">
                        <input type="date" name="check_in" x-model="checkIn" :min="today"
                               @click="$event.target.showPicker && $event.target.showPicker()"
                               class="w-full min-w-0 bg-transparent text-body-sm font-semibold text-black focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
                    </div>
                </div>
                <div class="flex min-w-[200px] flex-1 flex-col justify-center rounded-[6px] border-[0.5px] border-black/20 bg-[#f6f6f6]/[0.87] px-[25px] py-[11px]">
                    <label class="text-body-sm font-medium tracking-tight text-black">Check-out Date</label>
                    <div class="flex items-center gap-[7px]">
                        <img loading="lazy" src="{{ asset('images/date.png') }}" alt="" class="icon-sm shrink-0 object-contain">
                        <input type="date" name="check_out" x-model="checkOut" :min="checkIn || today"
                               @click="$event.target.showPicker && $event.target.showPicker()"
                               class="w-full min-w-0 bg-transparent text-body-sm font-semibold text-black focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
                    </div>
                </div>
                <button type="submit" :disabled="!datesValid || (availChecked && !available)"
                        class="flex min-h-[78px] min-w-[200px] items-center justify-center rounded-[6px] bg-[#ba6d04] px-6 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03] sm:min-w-[279px]"
                        :class="(!datesValid || (availChecked && !available)) ? 'opacity-50 cursor-not-allowed hover:bg-[#ba6d04]' : ''">
                    <span x-show="!datesValid">Select dates</span>
                    <span x-show="datesValid && !(availChecked && !available)" x-cloak>Make reservation</span>
                    <span x-show="datesValid && availChecked && !available" x-cloak>Unavailable</span>
                </button>
            </form>

            {{-- Live date-range availability / validation --}}
            <p x-show="(checkIn && checkOut && !datesValid) || availChecked" x-cloak class="-mt-2 text-body-sm tracking-tight">
                <span x-show="checkIn && checkOut && !datesValid" class="font-semibold text-[#ff8a8a]">Check-out date must be after the check-in date.</span>
                <template x-if="datesValid">
                    <span>
                        <span x-show="availChecked && available && availCount !== null" class="font-semibold text-[#7ee0a1]" x-text="availCount + (availCount === 1 ? ' room' : ' rooms') + ' available for these dates'"></span>
                        <span x-show="availChecked && available && availCount === null" class="text-white/60">Available for these dates</span>
                        <span x-show="availChecked && !available" class="font-semibold text-[#ff8a8a]">Fully booked for these dates — please choose different dates.</span>
                    </span>
                </template>
            </p>

            {{-- Airport pickup trigger (shown until a vehicle is selected) --}}
            <button type="button" x-show="!pickup" @click="airportModal = true" class="flex w-fit cursor-pointer items-center gap-1.5 text-white transition hover:text-[#f38c00]">
                <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                <span class="text-body-lg font-bold tracking-tight">Vehicle Pickup</span>
            </button>

            {{-- Selected airport pick-up summary (Figma node 85:1299) --}}
            <div x-show="pickup" x-cloak
                 class="flex flex-col gap-3 rounded-[6px] bg-[#dadbda] px-[23px] py-3.5 text-[#232323] lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-8">
                <button type="button" @click="pickup = null" class="flex shrink-0 cursor-pointer items-center gap-1.5" title="Remove vehicle pickup">
                    <svg class="icon-lg shrink-0 text-[#191919]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-body-lg font-bold tracking-tight lg:text-body-lg">Vehicle Pickup</span>
                </button>
                <span class="flex items-center gap-2 text-body font-medium lg:text-body-lg">
                    Car Type:
                    <img loading="lazy" x-show="pickup && pickup.img" :src="pickup ? pickup.img : ''" alt="" class="h-[21px] w-[32px] shrink-0 object-contain">
                    <span x-text="pickup ? pickup.name : ''"></span>
                </span>
                <span class="text-body font-medium lg:text-body-lg">Arrival Date: <span x-text="fmtDate(arrivalDate)"></span></span>
                <span class="text-body font-medium lg:text-body-lg">Arrival Time: <span x-text="fmtTime(pickupTime)"></span></span>
                <span class="text-body-lg font-semibold text-[#191919] lg:ml-auto lg:text-title" x-text="pickup ? pickup.price : ''"></span>
            </div>

            {{-- Discount terms --}}
            <div class="flex flex-col gap-1.5">
                <p class="text-title font-semibold tracking-tight text-white">Discount Terms</p>
                <p class="flex flex-wrap items-center gap-x-2 text-body-lg">
                    <span class="font-medium tracking-tight text-white">Book for 3 days and get a discount of 50% on the overall checkout.</span>
                    <a href="#" class="tracking-tight text-[#5d9efa] hover:underline">Terms and Conditions apply</a>
                </p>
            </div>
        </x-layouts.container>

        {{-- ===================== AIRPORT PICK-UP POPUP ===================== --}}
        <div x-show="airportModal" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @keydown.escape.window="airportModal = false"
             class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-8 lg:py-12">
            {{-- backdrop click closes --}}
            <div class="absolute inset-0" @click="airportModal = false"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-[1440px] overflow-hidden rounded-2xl p-6 sm:p-8 lg:p-10"
                 style="background-image: linear-gradient(180deg, #131210 0%, #1b1b18 60%, #1e1e1e 100%);"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Header --}}
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-bold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('pickup.title') }}</h2>
                    <button type="button" @click="airportModal = false" aria-label="Close"
                            class="flex icon-xl shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/10 lg:icon-xl">
                        <svg class="icon-lg lg:icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Search / booking bar (Figma node 85:1991) --}}
                <x-airport-search-bar :book-action="'if (doSearch()) { airportModal = false; vehicleModal = true }'" />

                {{-- Content: car image (left) + heading/text + chauffeur image (right) --}}
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-img src="{{ cms_image('pickup.image_1') }}" alt="Premium chauffeur car"
                           sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                           class="h-[300px] w-full rounded-xl object-cover lg:h-full lg:min-h-[620px]" />

                    <div class="flex flex-col gap-[29px]">
                        <h3 class="text-4xl font-medium leading-tight tracking-tight text-white lg:text-display lg:leading-[60px]">
                            {{ cms('pickup.heading') }}
                        </h3>
                        <p class="text-lg leading-relaxed tracking-tight text-white/90 lg:text-body-lg">
                            {{ cms('pickup.text') }}
                        </p>
                        <x-img src="{{ cms_image('pickup.image_2') }}" alt="Chauffeur assisting guest"
                               sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                               class="h-[280px] w-full rounded-xl object-cover lg:h-auto lg:flex-1" />
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== VEHICLE SELECTION (after "Book") ===================== --}}
        @php
            // Fleet is managed in the admin (Airport Pickups → Vehicles). Out-of-service
            // vehicles are excluded via the bookable() scope.
            $vehicles = \App\Models\Vehicle::query()->bookable()->ordered()->get()->map(fn ($v) => [
                'name' => $v->name,
                'price' => $v->priceLabel(),
                'seats' => $v->seats,
                'suitcases' => $v->suitcases,
                'img' => $v->imageUrl(),
                'free_cancellation' => $v->free_cancellation,
            ])->all();
        @endphp
        <div x-show="vehicleModal" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @keydown.escape.window="vehicleModal = false"
             class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/70 px-4 py-8 lg:py-12">
            {{-- backdrop click closes --}}
            <div class="absolute inset-0" @click="vehicleModal = false"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-[1440px] overflow-hidden rounded-2xl p-6 sm:p-8 lg:p-10"
                 style="background-image: linear-gradient(180deg, #131210 0%, #1b1b18 60%, #1e1e1e 100%);"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Header --}}
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-bold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('pickup.title') }}</h2>
                    <button type="button" @click="vehicleModal = false" aria-label="Close"
                            class="flex icon-xl shrink-0 items-center justify-center rounded-full text-white transition hover:bg-white/10 lg:icon-xl">
                        <svg class="icon-lg lg:icon-xl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Search / booking bar (Figma node 85:1991) --}}
                <x-airport-search-bar :book-action="'doSearch()'" />

                {{-- Prompt shown before the guest searches --}}
                <div x-show="!searched" x-cloak class="mt-6 flex flex-col items-center gap-2 rounded-[10px] bg-[#d2d2d2] px-6 py-14 text-center">
                    <svg class="h-10 w-10 text-[#7a7a7a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <p class="text-title font-semibold text-[#191919]">Search for available vehicles</p>
                    <p class="text-body text-[#5a5a5a]">Enter your arrival date, pick-up time and flight number above, then tap <span class="font-semibold">Book</span> to see available vehicles.</p>
                </div>

                {{-- Vehicle option cards (Figma node 85:1941 — managed in admin → Airport Pickups → Vehicles) --}}
                <div x-show="searched" x-cloak class="mt-6 flex flex-col gap-4">
                    @forelse ($vehicles as $v)
                        <div class="flex flex-col items-center gap-5 rounded-[14px] bg-[#dcdcd9] px-6 py-5 sm:flex-row sm:gap-8 lg:px-[55px] lg:py-7">
                            {{-- Vehicle image --}}
                            @if ($v['img'])
                                <img loading="lazy" src="{{ $v['img'] }}" alt="{{ $v['name'] }}"
                                     class="h-[110px] w-[230px] shrink-0 object-contain sm:h-[120px] sm:w-[250px]">
                            @else
                                <span class="flex h-[110px] w-[230px] shrink-0 items-center justify-center text-[#8a8a8a] sm:h-[120px] sm:w-[250px]">
                                    <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h2M17 17h2v-4M5 17h14"/><circle cx="8" cy="17" r="1.4"/><circle cx="16" cy="17" r="1.4"/></svg>
                                </span>
                            @endif

                            {{-- Name + spec meta --}}
                            <div class="flex flex-1 flex-col gap-3 text-center sm:text-left">
                                <p class="text-h3 font-bold tracking-tight text-[#1e1e1e] sm:text-h2">{{ $v['name'] }}</p>
                                <div class="flex flex-wrap items-center justify-center gap-x-7 gap-y-3 text-body font-medium tracking-tight text-[#1e1e1e] sm:justify-start sm:text-body-lg">
                                    @if ($v['free_cancellation'])
                                        <span class="flex items-center gap-2">
                                            <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="m6 6 12 12" stroke-linecap="round"/></svg>
                                            <span class="leading-tight">Free<br class="hidden sm:block">&nbsp;cancellation</span>
                                        </span>
                                    @endif
                                    <span class="flex items-center gap-2">
                                        <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M5 11a2 2 0 0 1 2-2h.5a2 2 0 0 1 2 1.6l.3 1.4h4.4l.3-1.4a2 2 0 0 1 2-1.6H19a2 2 0 0 1 2 2v6h-2v-3H7v3H5v-6zm2 7h2v2H7v-2zm8 0h2v2h-2v-2z"/></svg>
                                        <span><span class="font-semibold">{{ $v['seats'] }}</span> Seats</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v3M10 11v6M14 11v6" stroke-linecap="round"/></svg>
                                        <span><span class="font-semibold">{{ $v['suitcases'] }}</span> Suitcases</span>
                                    </span>
                                </div>
                            </div>

                            {{-- Price + select --}}
                            <div class="flex w-full shrink-0 flex-col items-center gap-3 sm:w-[230px]">
                                <p class="text-h2 font-semibold tracking-tight text-[#1e1e1e]">{{ $v['price'] }}</p>
                                <button type="button"
                                        @click="selectVehicle({ name: '{{ $v['name'] }}', price: '{{ $v['price'] }}', img: '{{ $v['img'] }}' })"
                                        class="flex h-[60px] w-full items-center justify-center rounded-[10px] bg-[#ba6d04] text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03]">
                                    Select
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-2 rounded-[14px] bg-[#dcdcd9] px-6 py-12 text-center">
                            <p class="text-title font-semibold text-[#191919]">No vehicles available right now</p>
                            <p class="text-body text-[#5a5a5a]">Vehicle pickup is temporarily unavailable. Please check back soon.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- =========================== DESCRIPTION =========================== --}}
    <section class="w-full pb-10">
        <x-layouts.container class="flex flex-col gap-[17px]">
            <h2 class="text-h3 font-semibold tracking-tight text-white">Description</h2>
            <p class="whitespace-pre-line text-lg leading-relaxed tracking-tight text-white/90 lg:text-body-lg">{{ $room->description }}</p>
        </x-layouts.container>
    </section>

    {{-- =========================== AMENITIES =========================== --}}
    <section class="w-full pb-10">
        <x-layouts.container class="flex flex-col gap-6">
            <h2 class="text-h3 font-semibold tracking-tight text-white">Apartment Amenities</h2>
            <div class="flex flex-wrap gap-[6px]">
                @foreach ($amenities as $a)
                    <span class="flex h-[54px] items-center gap-[5px] rounded-[6px] bg-[#dadbda] px-4 text-label font-medium tracking-tight text-black">
                        <span class="text-black">
                            @switch($a['icon'])
                                @case('fitness')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="currentColor"><path d="M20.57 14.86 22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z"/></svg>
                                    @break
                                @case('bed')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="currentColor"><path d="M21 10.78V8a2 2 0 0 0-2-2h-5v5h-4V6H5a2 2 0 0 0-2 2v2.78A2 2 0 0 0 2 12.5V19h2v-2h16v2h2v-6.5a2 2 0 0 0-1-1.72z"/></svg>
                                    @break
                                @case('wifi')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="currentColor"><path d="M12 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0-5c-1.66 0-3.16.67-4.24 1.76l1.41 1.41a4 4 0 0 1 5.66 0l1.41-1.41A5.98 5.98 0 0 0 12 13zm0-5c-3.04 0-5.79 1.23-7.78 3.22l1.41 1.41a8.97 8.97 0 0 1 12.73 0l1.41-1.41A10.97 10.97 0 0 0 12 8zm0-5C7.74 3 3.89 4.73 1.1 7.51L2.5 8.93a13.96 13.96 0 0 1 19 0l1.41-1.42A17.94 17.94 0 0 0 12 3z"/></svg>
                                    @break
                                @case('pool')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 18c1.5 0 1.5 1 3 1s1.5-1 3-1 1.5 1 3 1 1.5-1 3-1 1.5 1 3 1 1.5-1 3-1M7 15V5a2 2 0 0 1 4 0M13 13V5a2 2 0 0 1 4 0"/></svg>
                                    @break
                                @case('restaurant')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 3v7a2 2 0 0 0 2 2v9M6 3v6M8 3v6M18 3c-1.5 0-2.5 2-2.5 5s1 4 2.5 4v9"/></svg>
                                    @break
                                @case('parking')
                                    <svg class="icon-md" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5zm4 4h4a3 3 0 0 1 0 6h-2v4H9V7zm2 2v2h2a1 1 0 0 0 0-2h-2z"/></svg>
                                    @break
                                @default
                                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/></svg>
                            @endswitch
                        </span>
                        {{ $a['label'] }}
                    </span>
                @endforeach
            </div>
        </x-layouts.container>
    </section>

    {{-- =========================== ADDITIONAL =========================== --}}
    @php
        // Admin-managed "Additional" features. Old rooms (null) fall back to the
        // default set; an explicit empty array hides the section.
        $additionalItems = is_null($room->additional) ? [
            ['label' => 'Self-check-in', 'icon' => 'self_checkin'],
            ['label' => 'Vehicle pickup', 'icon' => 'airport'],
            ['label' => 'Private chef', 'icon' => 'chef'],
            ['label' => '24/7 House-keeping', 'icon' => 'housekeeping'],
        ] : $room->additional;
    @endphp
    @if (! empty($additionalItems))
        <section class="w-full pb-10">
            <x-layouts.container class="flex flex-col gap-[15px]">
                <h2 class="text-h3 font-semibold tracking-tight text-white">Additional</h2>
                <div class="flex flex-wrap items-center gap-x-[15px] gap-y-3 text-body-lg font-semibold tracking-tight text-white">
                    @foreach ($additionalItems as $item)
                        <span class="flex items-center gap-1.5">
                            @switch($item['icon'] ?? '')
                                @case('self_checkin')
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                                    @break
                                @case('airport')
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
                                    @break
                                @case('chef')
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a6 6 0 0 0-6 6c0 .35.03.69.08 1.02A3.5 3.5 0 0 0 7 17h10a3.5 3.5 0 0 0 .92-6.98c.05-.33.08-.67.08-1.02a6 6 0 0 0-6-6zM7 19h10v2H7z"/></svg>
                                    @break
                                @case('housekeeping')
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V10l9-7 9 7v11M9 21v-6h6v6"/></svg>
                                    @break
                                @default
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            @endswitch
                            {{ $item['label'] ?? '' }}
                        </span>
                        @if (! $loop->last)
                            <span class="hidden h-5 w-px bg-white/30 sm:block"></span>
                        @endif
                    @endforeach
                </div>
            </x-layouts.container>
        </section>
    @endif

    {{-- ======================= CANCELLATION POLICY ======================= --}}
    @php
        $cancellationPolicy = trim((string) $room->cancellation_policy)
            ?: 'Free cancellation up to 48 hours before check-in. Cancellations made within 48 hours of arrival, or no-shows, are charged the first night. Refunds are processed to your original payment method within 5–7 business days.';
    @endphp
    <section class="w-full pb-12 lg:pb-16">
        <x-layouts.container>
            <div class="rounded-[10px] bg-[rgba(113,113,113,0.27)] px-6 py-7 lg:px-10 lg:py-9">
                <p class="text-title font-semibold tracking-tight text-white lg:text-h3">Cancellation Policy</p>
                <p class="mt-5 whitespace-pre-line text-lg leading-snug tracking-tight text-[#dadbda] lg:text-body-lg">{{ $cancellationPolicy }}</p>
            </div>
        </x-layouts.container>
    </section>

    {{-- ===================== EXPLORE OUR EXCLUSIVE OFFERS ===================== --}}
    <section class="w-full py-12 lg:py-16">
        <x-layouts.container class="flex flex-col gap-[50px] lg:gap-[68px]">
            <h2 class="text-center text-2xl tracking-tight text-white sm:text-3xl lg:text-h2">Explore our exclusive offers</h2>
            <div class="grid grid-cols-1 gap-[15px] md:grid-cols-2">
                @foreach ($offers as $offer)
                    <x-layouts.room-card :room="$offer" />
                @endforeach
            </div>
        </x-layouts.container>
    </section>
</x-layouts.web>
