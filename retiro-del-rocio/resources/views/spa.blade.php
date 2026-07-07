<x-layouts.web title="Spa & Wellness — Retiro Del Rocio"
    description="Rejuvenate mind, body and soul at Retiro Del Rocio. Explore our spa services — skincare, massage, sauna baths and more — and discover a healthier way to relax in Jos.">

    @php
        $services = [
            ['title' => cms('spa.service_1_title'), 'image' => cms_image('spa.service_1_image')],
            ['title' => cms('spa.service_2_title'), 'image' => cms_image('spa.service_2_image')],
            ['title' => cms('spa.service_3_title'), 'image' => cms_image('spa.service_3_image')],
            ['title' => cms('spa.service_4_title'), 'image' => cms_image('spa.service_4_image')],
        ];
        $features = cms_array('spa.features');

        // Decorative icons for the "Why us" features (match the Figma, by position).
        $featureIcons = [
            asset('images/products.png'),
            asset('images/temaki_spa.png'),
            asset('images/treatments.png'),
            asset('images/ic_twotone-spa.png'),
        ];

        // Bookable spa services for the "Book Session" reservation popup.
        $spaServices = \App\Models\SpaService::active()->ordered()->get();
        $spaServicesJson = $spaServices->map(fn ($s) => [
            'slug' => $s->slug,
            'name' => $s->name,
            'price' => $s->price,
            'image' => $s->imageUrl(),
            'description' => $s->description,
        ])->values();

        // Server-driven popup steps: select → checkout → success.
        // The order is consumed once (read then forgotten) so the success step
        // only shows right after payment — never again on reload.
        $spaOrder = session('spa_success');
        session()->forget(['spa_success', 'spa_order']); // consume fresh order + clear any legacy stale value

        $spaBooking = session('spa_booking');
        $spaStep = $spaOrder ? 'success' : ($spaBooking ? 'checkout' : 'select');
        $paystackKey = config('services.paystack.public_key');
    @endphp

    <div x-data="spaReservation({
            services: @js($spaServicesJson),
            fees: 2000,
            step: @js($spaStep),
            paystackKey: @js($paystackKey),
            callbackUrl: @js(route('spa.checkout.callback')),
            bookingKobo: {{ (int) ($spaBooking['total_kobo'] ?? 0) }},
            bookingServices: @js($spaBooking ? collect($spaBooking['services'])->pluck('name')->implode(', ') : ''),
            bookingDateLabel: @js($spaBooking['date_label'] ?? ''),
            bookingSelected: @js($spaBooking ? collect($spaBooking['services'])->pluck('slug')->values()->all() : []),
            bookingGuests: {{ (int) ($spaBooking['guests'] ?? 2) }},
            bookingDate: @js($spaBooking['date'] ?? ''),
            bookingTime: @js($spaBooking['time'] ?? ''),
         })">


    {{-- ============================ HERO ============================ --}}
    <section class="relative w-full">
        <x-img src="{{ cms_image('spa.hero_image') }}" alt="Spa & Wellness" sizes="100vw"
               loading="eager" fetchpriority="high"
               class="h-[460px] w-full object-cover sm:h-[600px] lg:h-[720px]" />
        <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/45 to-black/10"></div>
        <x-layouts.container class="absolute inset-0 flex items-center">
            <div class="flex max-w-[640px] flex-col gap-6">
                <h1 class="text-4xl font-semibold leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[1.05]">
                    {{ cms('spa.hero_title') }}
                </h1>
                <p class="max-w-[520px] text-lg leading-relaxed tracking-tight text-white/90 lg:text-body-lg">
                    {{ cms('spa.hero_text') }}
                </p>
                <button type="button" @click="step = 'select'; open()"
                        class="flex w-fit items-center gap-2.5 rounded-[10px] bg-[#ba6d04] px-8 py-4 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03]">
                    {{ cms('spa.hero_cta_label') }}
                    <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </div>
        </x-layouts.container>
    </section>

    {{-- ===================== EXPLORE OUR SPA SERVICES ===================== --}}
    <section class="w-full bg-[#1a1a1a] bg-cover bg-top bg-no-repeat py-16 lg:py-24"
             style="background-image: url('{{ asset('images/spabg.png') }}');">
        <x-layouts.container class="flex flex-col gap-10 lg:gap-14">
            <div class="mx-auto flex max-w-[920px] flex-col gap-4 text-center">
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('spa.services_title') }}</h2>
                <p class="text-base leading-relaxed tracking-tight text-white/70 lg:text-body-lg">{{ cms('spa.services_text') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($services as $service)
                    <div class="flex flex-col gap-4">
                        <div class="overflow-hidden rounded-2xl">
                            @if ($service['image'])
                                <x-img src="{{ $service['image'] }}" alt="{{ $service['title'] }}"
                                       sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" loading="lazy" decoding="async"
                                       class="h-[300px] w-full object-cover transition duration-300 hover:scale-105 lg:h-[419px]" />
                            @else
                                <div class="flex h-[300px] w-full items-center justify-center bg-[#373d35] lg:h-[419px]">
                                    <svg class="size-10 text-white/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                                </div>
                            @endif
                        </div>
                        <p class="text-center text-title font-semibold tracking-tight text-white lg:text-h3">{{ $service['title'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-layouts.container>
    </section>

    {{-- ================= DISCOVER A HEALTHIER WAY TO RELAX ================= --}}
    <section class="relative w-full">
        <x-img src="{{ cms_image('spa.discover_image') }}" alt="" sizes="100vw" loading="lazy" decoding="async"
               class="h-[460px] w-full object-cover lg:h-[560px]" />
        <div class="absolute inset-0 bg-black/65"></div>
        <x-layouts.container class="absolute inset-0 flex items-center">
            <div class="grid grid-cols-1 items-center gap-6 lg:grid-cols-2 lg:gap-16">
                <p class="order-2 text-lg leading-relaxed tracking-tight text-white/90 lg:order-1 lg:text-body-lg">
                    {{ cms('spa.discover_text') }}
                </p>
                <h2 class="order-1 text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:order-2 lg:text-h1">
                    {{ cms('spa.discover_title') }}
                </h2>
            </div>
        </x-layouts.container>
    </section>

    {{-- ========================= WHY RETIRO DEL ROCIO ========================= --}}
    <section class="relative w-full overflow-hidden py-16 text-black lg:py-20"
             style="background-image: linear-gradient(95deg, #feefe4 2%, #fafaee 27%, #fafaee 68%, #f6d7c3 99%);">
        {{-- Faint spa background image overlay --}}
        <x-img src="{{ asset('images/spa/why-bg.jpg') }}" alt="" sizes="100vw"
             loading="lazy" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-[0.09]" />

        <x-layouts.container class="relative flex flex-col items-center gap-10 lg:gap-12">
            <h2 class="text-center text-[clamp(28px,4vw,41px)] font-light tracking-tight text-black">{{ cms('spa.why_title') }}</h2>

            <div class="grid w-full grid-cols-1 gap-x-7 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($features as $i => $feature)
                    <div class="flex flex-col items-center gap-2 text-center">
                        <span class="flex h-[88px] items-end justify-center">
                            <img loading="lazy" src="{{ $featureIcons[$i % count($featureIcons)] }}" alt="" class="max-h-[88px] w-auto object-contain">
                        </span>
                        <h3 class="text-2xl font-semibold tracking-tight text-black lg:text-[30px]">{{ $feature['title'] ?? '' }}</h3>
                        <p class="max-w-[340px] text-base leading-snug tracking-tight text-black lg:text-[19px]">{{ $feature['text'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </x-layouts.container>
    </section>

    {{-- ============================ BOOK SESSION POPUP ============================ --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/70 px-3 py-6 sm:px-6 sm:py-10"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @keydown.escape.window="close()">
        <div class="relative w-full max-w-[1100px] overflow-hidden rounded-[20px] bg-[#1e1e1e] shadow-2xl"
             @click.outside="close()"
             x-show="showModal"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-6 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]">
            {{-- Shared close button --}}
            <button type="button" @click="close()" class="absolute right-5 top-5 z-20 flex size-11 shrink-0 items-center justify-center rounded-full border border-white/40 text-white transition hover:bg-white/10">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>

            {{-- ===================== STEP 1: SELECT SERVICES ===================== --}}
            <div x-show="step === 'select'" class="p-6 sm:p-9 lg:p-11"
                 style="background-image: linear-gradient(180deg, #ffcb8e 0px, #ffcb8e 70px, #1e1e1e 210px, #1e1e1e 100%);">
                {{-- Header --}}
                <div class="flex flex-col gap-2 pr-12">
                    <h2 class="text-3xl font-semibold tracking-tight text-[#FFFFFF] sm:text-4xl lg:text-h1">{{ cms('spares.title') }}</h2>
                    <p class="max-w-[760px] text-body font-medium text-[#FFFFFF] lg:text-body-lg">{{ cms('spares.intro') }}</p>
                </div>

                {{-- Guests / Date / Time --}}
                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <label class="flex flex-col gap-2">
                        <span class="text-body font-semibold text-white">Number of Guest</span>
                        <div class="flex h-[60px] items-center gap-3 rounded-[11px] bg-[#ececec] px-4">
                            <svg class="size-6 shrink-0 text-[#6a6a6a]" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 2c-2.7 0-8 1.3-8 4v3h8v-3c0-1 .4-1.9 1-2.7-.3 0-.7-.3-1-.3zm8 0c-.3 0-.7 0-1 .1 1 .8 1.6 1.7 1.6 2.9v3H24v-3c0-2.7-5.3-4-8-4z"/></svg>
                            <select x-model.number="guests" class="h-full w-full bg-transparent text-body-lg font-bold text-[#6a6a6a] focus:outline-none">
                                @for ($g = 1; $g <= 10; $g++)
                                    <option value="{{ $g }}">{{ $g }} {{ \Illuminate\Support\Str::plural('Guest', $g) }}</option>
                                @endfor
                            </select>
                        </div>
                    </label>
                    <label class="flex flex-col gap-2">
                        <span class="text-body font-semibold text-white">Date</span>
                        <div class="flex h-[60px] items-center gap-3 rounded-[11px] bg-[#ececec] px-4">
                            <svg class="size-6 shrink-0 text-[#6a6a6a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                            <input type="date" x-model="date" min="{{ now()->toDateString() }}" class="h-full w-full bg-transparent text-body-lg font-bold text-[#6a6a6a] focus:outline-none">
                        </div>
                    </label>
                    <label class="flex flex-col gap-2">
                        <span class="text-body font-semibold text-white">Time</span>
                        <div class="flex h-[60px] items-center gap-3 rounded-[11px] bg-[#ececec] px-4">
                            <svg class="size-6 shrink-0 text-[#6a6a6a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <input type="time" x-model="time" class="h-full w-full bg-transparent text-body-lg font-bold text-[#6a6a6a] focus:outline-none">
                        </div>
                    </label>
                </div>

                {{-- Select Spa Service (multi-select carousel) --}}
                <p class="mt-8 text-body-lg font-semibold text-white">{{ cms('spares.service_label') }}</p>
                <div class="relative mt-4"
                     x-data="{
                        canLeft: false,
                        canRight: true,
                        refresh() {
                            const t = $refs.track;
                            if (!t) return;
                            this.canLeft = t.scrollLeft > 4;
                            this.canRight = (t.scrollLeft + t.clientWidth) < (t.scrollWidth - 4);
                        },
                        slide(dir) {
                            const t = $refs.track;
                            const card = t.querySelector('[data-spa-card]');
                            const amount = card ? (card.offsetWidth + 16) : 300;
                            t.scrollBy({ left: dir * amount, behavior: 'smooth' });
                        }
                     }"
                     x-init="$nextTick(() => refresh())"
                     x-effect="if (showModal && step === 'select') $nextTick(() => setTimeout(() => refresh(), 60))">
                    {{-- Left arrow --}}
                    <button type="button" @click="slide(-1)" x-show="canRight || canLeft" :disabled="!canLeft"
                            aria-label="Previous services"
                            class="absolute -left-2 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 sm:flex">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>

                    {{-- Track --}}
                    <div x-ref="track" @scroll.debounce.50ms="refresh()"
                         class="no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-1">
                        <template x-for="s in services" :key="s.slug">
                            <button type="button" data-spa-card @click="toggle(s.slug)"
                                    class="relative flex w-[230px] shrink-0 snap-start flex-col items-center gap-4 rounded-[11px] bg-[#f6f9fc] p-5 text-center transition sm:w-[250px]"
                                    :class="isSelected(s.slug) ? 'ring-2 ring-[#ba6d04]' : 'ring-1 ring-transparent hover:ring-[#ba6d04]/40'">
                                <span class="absolute left-3 top-3 flex size-6 items-center justify-center rounded-full border-2 transition"
                                      :class="isSelected(s.slug) ? 'border-[#ba6d04] bg-[#ba6d04] text-white' : 'border-[#cbd5e1] bg-white'">
                                    <svg x-show="isSelected(s.slug)" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                </span>
                                <span class="h-28 w-full overflow-hidden rounded-lg bg-[#e9edf1]">
                                    <img :src="s.image" :alt="s.name" class="h-full w-full object-cover" x-show="s.image">
                                </span>
                                <span class="text-[20px] font-bold text-[#343a40]" x-text="s.name"></span>
                                <span class="line-clamp-2 text-[13px] text-[#5a5a5a]" x-text="s.description"></span>
                                <span class="flex flex-col">
                                    <span class="text-[24px] font-semibold text-[#222]" x-text="money(s.price)"></span>
                                    <span class="text-[12px] text-[#6a6a6a]">Per Guest</span>
                                </span>
                            </button>
                        </template>
                    </div>

                    {{-- Right arrow --}}
                    <button type="button" @click="slide(1)" x-show="canRight || canLeft" :disabled="!canRight"
                            aria-label="Next services"
                            class="absolute -right-2 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 sm:flex">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>

                {{-- Special request --}}
                <div class="mt-8 flex flex-col gap-3">
                    <p class="text-body-lg font-semibold text-white">{{ cms('spares.special_label') }} <span class="font-light italic text-white/60">(Optional)</span></p>
                    <textarea x-model="special" rows="3" placeholder="Please let us know if you have any special request or preferences."
                              class="w-full rounded-[11px] bg-[#ececec] p-4 text-body text-[#3a3a3a] placeholder:italic placeholder:text-[#6a6a6a] focus:outline-none"></textarea>
                </div>

                {{-- Reservation Summary --}}
                <div class="mt-9 flex flex-col gap-2 border-t border-white/10 pt-7">
                    <h3 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl lg:text-h2">{{ cms('spares.summary_title') }}</h3>
                    <p class="max-w-[820px] text-body text-white/70 lg:text-body-lg">{{ cms('spares.summary_text') }}</p>
                </div>

                {{-- Summary --}}
                <div class="mt-6 flex w-full flex-col gap-6">
                    <div class="flex w-full flex-col gap-4">
                        <p class="text-xl font-semibold tracking-tight text-white lg:text-h3">Order Details</p>
                        {{-- Service --}}
                        <div class="flex flex-col gap-2.5">
                            <p class="text-body font-medium text-[#f38c00]">Service</p>
                            <template x-for="s in chosen" :key="'sum-'+s.slug">
                                <div class="flex items-center justify-between gap-4 text-body text-white">
                                    <span x-text="s.name + ' (' + guests + ' ' + (guests > 1 ? 'Guests' : 'Guest') + ') :'"></span>
                                    <span class="font-semibold" x-text="money(s.price * guests)"></span>
                                </div>
                            </template>
                            <p x-show="chosen.length === 0" class="text-body text-white/50">No service selected yet.</p>
                        </div>

                        {{-- Reservation --}}
                        <div class="flex flex-col gap-2.5 border-t border-white/15 pt-4 text-body text-white">
                            <p class="font-medium text-[#f38c00]">Reservation</p>
                            <div class="flex items-center justify-between"><span>Number of Guest:</span><span class="font-medium" x-text="guests"></span></div>
                            <div class="flex items-center justify-between"><span>Date:</span><span class="font-medium" x-text="date || '—'"></span></div>
                            <div class="flex items-center justify-between"><span>Time:</span><span class="font-medium" x-text="timeLabel || '—'"></span></div>
                        </div>

                        {{-- Fees & taxes --}}
                        <div class="flex flex-col gap-1.5 border-t border-white/15 pt-4 text-body text-white">
                            <div class="flex items-center justify-between"><span class="text-[#f38c00]">Convenience Fee:</span><span x-text="money(subtotal ? fees : 0)"></span></div>
                            <div class="flex items-center justify-between"><span class="text-[#f38c00]">Taxes (VAT 7.5%):</span><span x-text="money(taxes)"></span></div>
                        </div>
                    </div>

                    {{-- Total + Complete Reservation on one line --}}
                    <div class="flex flex-wrap items-center justify-between gap-5 border-t border-white/15 pt-5">
                        <div class="flex items-baseline gap-3">
                            <span class="text-body-lg font-medium text-[#f38c00]">TOTAL</span>
                            <span class="text-3xl font-semibold tracking-tight text-white lg:text-h2" x-text="money(total)"></span>
                        </div>
                        <button type="button" @click="submit()" :disabled="!canSubmit"
                                class="flex h-[64px] min-w-[260px] items-center justify-center gap-2 rounded-[10px] bg-[#ba6d04] px-8 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03] disabled:cursor-not-allowed disabled:opacity-50">
                            {{ cms('spares.cta_label') }}
                            <svg class="icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            {{-- /step select --}}

            {{-- ===================== STEP 2: CHECKOUT (pay) ===================== --}}
            @if ($spaBooking)
                @php $coImg = $spaBooking['services'][0]['image'] ?? null; @endphp
                <div x-show="step === 'checkout'" x-cloak class="p-6 sm:p-9 lg:p-11"
                     style="background-image: linear-gradient(180deg, #ffcb8e 0px, #ffcb8e 70px, #1e1e1e 210px, #1e1e1e 100%);">
                    <div class="flex flex-col gap-2 pr-12">
                        <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('spacheckout.checkout_heading') }}</h2>
                        <button type="button" @click="editSelection()" class="flex w-fit items-center gap-2 text-body font-semibold text-white/80 transition hover:text-[#f38c00]">
                            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                            Edit selection
                        </button>
                    </div>

                    <div class="mt-7 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        {{-- Left: image + order summary --}}
                        <div class="flex flex-col gap-5">
                            @if ($coImg)
                                <x-img src="{{ $coImg }}" alt="" sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async" class="h-[200px] w-full rounded-xl object-cover" />
                            @endif
                            <div class="flex flex-col gap-4 rounded-xl bg-[#373d35]/40 p-5 text-white">
                                <p class="text-lg font-semibold">Order Details</p>
                                <div class="flex flex-col gap-2">
                                    <p class="text-body font-medium text-[#f38c00]">Service</p>
                                    @foreach ($spaBooking['services'] as $s)
                                        <div class="flex items-center justify-between gap-4 text-body"><span>{{ $s['name'] }} ({{ $spaBooking['guests'] }} {{ \Illuminate\Support\Str::plural('Guest', $spaBooking['guests']) }}) :</span><span class="font-semibold">{{ $s['subtotal_label'] }}</span></div>
                                    @endforeach
                                </div>
                                <div class="flex flex-col gap-2 border-t border-white/15 pt-3 text-body">
                                    <p class="font-medium text-[#f38c00]">Reservation</p>
                                    <div class="flex items-center justify-between"><span>Number of Guest:</span><span class="font-medium">{{ $spaBooking['guests'] }}</span></div>
                                    <div class="flex items-center justify-between"><span>Date:</span><span class="font-medium">{{ $spaBooking['date_label'] }}</span></div>
                                    @if ($spaBooking['time'])<div class="flex items-center justify-between"><span>Time:</span><span class="font-medium">{{ $spaBooking['time_label'] ?? $spaBooking['time'] }}</span></div>@endif
                                </div>
                                <div class="flex flex-col gap-1.5 border-t border-white/15 pt-3 text-body">
                                    <div class="flex items-center justify-between"><span class="text-[#f38c00]">Convenience Fee:</span><span>{{ $spaBooking['fees_label'] }}</span></div>
                                    <div class="flex items-center justify-between"><span class="text-[#f38c00]">Taxes (VAT 7.5%):</span><span>{{ $spaBooking['taxes_label'] }}</span></div>
                                </div>
                                <div class="flex items-center justify-between border-t border-white/15 pt-3"><span class="text-body-lg font-semibold text-[#f38c00]">TOTAL</span><span class="text-h3 font-semibold">{{ $spaBooking['total_label'] }}</span></div>
                            </div>
                        </div>

                        {{-- Right: customer + payment --}}
                        <div class="flex flex-col gap-5">
                            <div class="rounded-xl bg-[#373d35] p-5 sm:p-6">
                                <h3 class="text-h3 font-semibold text-white">{{ cms('spacheckout.customer_title') }}</h3>
                                <div class="mt-5 flex flex-col gap-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2"><span class="text-label text-[#a5a5a5]">First Name</span><input type="text" x-model="firstName" placeholder="Micheal" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none"></label>
                                        <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2"><span class="text-label text-[#a5a5a5]">Last Name</span><input type="text" x-model="lastName" placeholder="Philips" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none"></label>
                                    </div>
                                    <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2"><span class="text-label text-[#a5a5a5]">Email Address</span><input type="email" x-model="email" placeholder="micheal.philips@gmail.com" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none"></label>
                                    <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2"><span class="text-label text-[#a5a5a5]">Phone Number</span><div class="flex items-center gap-2"><span class="shrink-0 text-body-lg text-white">🇳🇬 +234</span><input type="tel" x-model="phone" placeholder="8143432903" inputmode="numeric" class="w-full bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none"></div></label>
                                </div>
                            </div>

                            <div class="rounded-xl bg-[rgba(113,113,113,0.27)] p-5 sm:p-6">
                                <h3 class="text-title font-semibold text-white">{{ cms('spacheckout.cancellation_title') }}</h3>
                                <p class="mt-3 text-body leading-snug text-[#dadbda]">{{ cms('spacheckout.cancellation_text') }}</p>
                            </div>

                            <div class="rounded-xl bg-[#373d35] p-5 sm:p-6">
                                <h3 class="text-h3 font-semibold text-white">Payment Options</h3>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" @click="channel = 'card'" :class="channel === 'card' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-semibold transition">Card</button>
                                    <button type="button" @click="channel = 'bank'" :class="channel === 'bank' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Bank</button>
                                    <button type="button" @click="channel = 'transfer'" :class="channel === 'transfer' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Transfer</button>
                                </div>
                                <p class="mt-4 flex items-center gap-2 text-label text-white/60">
                                    <svg class="icon-xs shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/></svg>
                                    {{ cms('spacheckout.secure_note') }}
                                </p>
                                <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                                    <button type="button" @click="pay()" class="flex h-[64px] min-w-[220px] flex-1 items-center justify-center rounded-[8px] bg-[#ba6d04] text-body-lg font-semibold text-white transition hover:bg-[#a35f03] sm:flex-none">{{ cms('spacheckout.pay_label') }}</button>
                                    <div class="flex flex-col text-white"><span class="text-body font-semibold">Total</span><span class="text-h3 font-semibold">{{ $spaBooking['total_label'] }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===================== STEP 3: SUCCESS (solid dark, no gradient) ===================== --}}
            @if ($spaOrder)
                <div x-show="step === 'success'" x-cloak class="px-6 pb-10 pt-16 sm:px-10 sm:pb-12 sm:pt-20 lg:px-14 lg:pb-14 lg:pt-24">
                    <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-[1fr_441px] lg:gap-[78px]">
                        {{-- Left: Order Details --}}
                        <div class="flex flex-col gap-7 text-white">
                            <p class="text-2xl font-semibold tracking-tight lg:text-h3">Order Details</p>

                            <div class="flex flex-col gap-2.5">
                                <p class="text-body font-medium text-[#f38c00]">Service</p>
                                @foreach ($spaOrder['services'] as $s)
                                    <div class="flex items-center justify-between gap-4 text-body lg:text-body-lg">
                                        <span>{{ $s['name'] }} ({{ $spaOrder['guests'] }} {{ \Illuminate\Support\Str::plural('Guest', $spaOrder['guests']) }}) :</span>
                                        <span class="font-semibold">{{ $s['subtotal_label'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-col gap-2.5 border-t border-white/15 pt-5 text-body lg:text-body-lg">
                                <p class="font-medium text-[#f38c00]">Reservation</p>
                                <div class="flex items-center justify-between"><span>Number of Guest:</span><span class="font-medium">{{ $spaOrder['guests'] }}</span></div>
                                <div class="flex items-center justify-between"><span>Date:</span><span class="font-medium">{{ $spaOrder['date_label'] }}</span></div>
                                @if ($spaOrder['time'])<div class="flex items-center justify-between"><span>Time:</span><span class="font-medium">{{ $spaOrder['time_label'] ?? $spaOrder['time'] }}</span></div>@endif
                            </div>

                            <div class="flex items-center justify-end gap-4 border-t border-white/15 pt-5">
                                <span class="text-body-lg font-medium text-[#f38c00]">TOTAL</span>
                                <span class="text-h3 font-semibold tracking-tight lg:text-h2">{{ $spaOrder['total_label'] }}</span>
                            </div>
                        </div>

                        {{-- Right: success + ID + customer + actions --}}
                        <div class="flex flex-col items-center gap-6 text-center">
                            <img loading="lazy" src="{{ asset('images/checkcircle.png') }}" alt="Success" class="size-[120px] object-contain lg:size-[150px]">
                            <div class="flex flex-col gap-1.5">
                                <h2 class="text-h3 font-bold tracking-tight text-[#f38c00] lg:text-h2">{{ cms('spacheckout.success_title') }}</h2>
                                <p class="text-body text-white/80">{{ cms('spacheckout.success_text') }}</p>
                            </div>

                            <div class="flex flex-col items-center gap-1">
                                <span class="text-label uppercase tracking-wide text-white/50">Booking ID</span>
                                <span class="text-2xl font-bold tracking-tight text-white lg:text-3xl">{{ $spaOrder['code'] ?? '—' }}</span>
                            </div>

                            <div class="flex w-full flex-col gap-2 border-t border-white/15 pt-5 text-body text-white/80">
                                <p class="font-medium text-white">Customer Details</p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Name</span><span>{{ $spaOrder['customer_name'] ?: '—' }}</span></p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Contact number</span><span>{{ $spaOrder['customer_phone'] ?: '—' }}</span></p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Email Address</span><span class="break-all">{{ $spaOrder['customer_email'] ?: '—' }}</span></p>
                            </div>

                            <div class="flex w-full flex-col gap-5">
                                <button type="button" onclick="window.print()" class="flex h-[70px] w-full items-center justify-center gap-2.5 rounded-[6px] bg-[#ba6d04] text-body-lg font-semibold text-white transition hover:bg-[#a35f03]">
                                    Download Receipt
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                </button>
                                <a href="{{ route('home') }}" wire:navigate class="flex h-[70px] w-full items-center justify-center rounded-[6px] border border-white text-body-lg font-medium text-white transition hover:bg-white/10">Back to Homepage</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        {{-- /panel --}}

        {{-- Hidden form submitted on "Complete Reservation" --}}
        <form x-ref="form" method="POST" action="{{ route('spa.checkout.start') }}" class="hidden">
            @csrf
            <template x-for="slug in selected" :key="'f-'+slug"><input type="hidden" name="services[]" :value="slug"></template>
            <input type="hidden" name="guests" :value="guests">
            <input type="hidden" name="date" :value="date">
            <input type="hidden" name="time" :value="time">
            <input type="hidden" name="special_request" :value="special">
        </form>
    </div>
    {{-- /Book Session popup --}}
    </div>
    {{-- /spaReservation wrapper --}}

    @if (! empty($paystackKey))
        @push('scripts')
            <script src="https://js.paystack.co/v1/inline.js"></script>
        @endpush
    @endif
</x-layouts.web>
