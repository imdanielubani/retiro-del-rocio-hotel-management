<x-layouts.web title="Gym & Fitness — Retiro Del Rocio"
    description="Wellness that fits your lifestyle. Choose a Retiro Del Rocio gym membership — expert-led programs, modern facilities and flexible plans in Jos.">

    @php
        $plans = \App\Models\GymPlan::active()->ordered()->get();
        $plansJson = $plans->map(fn ($p) => [
            'slug' => $p->slug, 'name' => $p->name, 'price' => $p->price,
            'price_label' => $p->priceLabel(), 'period' => $p->period, 'period_short' => $p->periodShort(),
            'tagline' => $p->tagline, 'features' => $p->featureList(),
        ])->values();

        $gymSuccess = session('gym_success');
        session()->forget('gym_success'); // consume once

        $paystackKey = config('services.paystack.public_key');

        $offers = [
            ['icon' => 'yoga', 'title' => cms('gym.offer_1_title'), 'text' => cms('gym.offer_1_text')],
            ['icon' => 'trainer', 'title' => cms('gym.offer_2_title'), 'text' => cms('gym.offer_2_text')],
            ['icon' => 'weight', 'title' => cms('gym.offer_3_title'), 'text' => cms('gym.offer_3_text')],
            ['icon' => 'cardio', 'title' => cms('gym.offer_4_title'), 'text' => cms('gym.offer_4_text')],
        ];
    @endphp

    <div x-data="gymMembership({
            plans: @js($plansJson),
            paystackKey: @js($paystackKey),
            successData: @js($gymSuccess),
         })">

        {{-- ============================ HERO ============================ --}}
        <section class="relative w-full">
            <x-img src="{{ cms_image('gym.hero_image') }}" alt="Gym & Fitness" sizes="100vw"
                   loading="eager" fetchpriority="high"
                   class="h-[520px] w-full object-cover sm:h-[620px] lg:h-[720px]" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/20"></div>
            <x-layouts.container class="absolute inset-0 flex items-center">
                <div class="flex max-w-[640px] flex-col gap-6">
                    <h1 class="text-4xl font-semibold leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[1.05]">{{ cms('gym.hero_title') }}</h1>
                    <p class="max-w-[560px] text-lg leading-relaxed tracking-tight text-white/90 lg:text-body-lg">{{ cms('gym.hero_text') }}</p>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="button" @click="open('subscribe')"
                                class="rounded-[12px] bg-[#f38c00] px-9 py-4 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#dd7f00]">
                            {{ cms('gym.subscribe_label') }}
                        </button>
                        <button type="button" @click="open('renewal')"
                                class="rounded-[12px] border border-white/70 px-9 py-4 text-body-lg font-semibold tracking-tight text-white transition hover:bg-white/10">
                            {{ cms('gym.renew_label') }}
                        </button>
                    </div>
                </div>
            </x-layouts.container>
        </section>

        {{-- ===================== ELEVATE band ===================== --}}
        <section class="w-full py-10 lg:py-16">
            <x-layouts.container class="grid grid-cols-1 gap-5 lg:grid-cols-[40%_60%] lg:items-center lg:gap-12">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('gym.elevate_title') }}</h2>
                <p class="text-body leading-relaxed tracking-tight text-white/70 lg:text-body-lg">{{ cms('gym.elevate_text') }}</p>
            </x-layouts.container>
        </section>

        {{-- Wide banner — sits on the solid page background, above the gradient (per Figma) --}}
        <section class="w-full pb-2 lg:pb-4">
            <x-layouts.container>
                <x-img src="{{ cms_image('gym.banner_image') }}" alt="" sizes="100vw" loading="lazy" decoding="async"
                       class="h-[280px] w-full rounded-[18px] object-cover sm:h-[420px] lg:h-[560px]" />
            </x-layouts.container>
        </section>

        {{-- ===================== FITNESS PLANS + WHAT WE OFFER (linear bg, per Figma) ===================== --}}
        <section class="w-full bg-gradient-to-b from-transparent via-black via-[16.409%] to-[#1e1e1e] to-[41.867%] py-16 lg:py-24">
            <x-layouts.container class="flex flex-col gap-12">
                <div class="flex flex-col items-center gap-3 text-center">
                    <p class="text-body font-semibold uppercase tracking-[1px] text-[#f38c00]">{{ cms('gym.plans_subtitle') }}</p>
                    <h2 class="max-w-[760px] text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('gym.plans_title') }}</h2>
                </div>

                <div class="relative"
                     x-data="{
                        canLeft: false,
                        canRight: true,
                        refresh() { const t = $refs.track; if (!t) return; this.canLeft = t.scrollLeft > 4; this.canRight = (t.scrollLeft + t.clientWidth) < (t.scrollWidth - 4); },
                        slide(dir) { const t = $refs.track; const c = t.querySelector('[data-plan-card]'); const amt = c ? (c.offsetWidth + 24) : 360; t.scrollBy({ left: dir * amt, behavior: 'smooth' }); }
                     }"
                     x-init="$nextTick(() => refresh())">
                    {{-- Left arrow --}}
                    <button type="button" @click="slide(-1)" x-show="canRight || canLeft" :disabled="!canLeft" aria-label="Previous plans"
                            class="absolute -left-3 top-1/2 z-10 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 lg:flex">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>

                    <div x-ref="track" @scroll.debounce.50ms="refresh()" class="no-scrollbar flex snap-x snap-mandatory items-stretch gap-6 overflow-x-auto scroll-smooth pb-2">
                    @foreach ($plans as $plan)
                        <div data-plan-card @class([
                                'flex w-[85%] shrink-0 snap-start flex-col rounded-[18px] p-7 sm:w-[360px] lg:w-[calc(33.333%-1rem)] lg:p-8',
                                'bg-[#f38c00] text-white shadow-2xl' => $plan->is_featured,
                                'border border-white/12 bg-[#20271d] text-white' => ! $plan->is_featured,
                            ])>
                            <h3 class="text-2xl font-semibold tracking-tight lg:text-h3">{{ $plan->name }}</h3>
                            <p @class(['mt-3 text-body leading-snug', 'text-white/85' => $plan->is_featured, 'text-white/60' => ! $plan->is_featured])>{{ $plan->tagline }}</p>
                            <div class="mt-6 flex items-end gap-1.5">
                                <span class="text-4xl font-semibold tracking-tight lg:text-[40px]">{{ $plan->priceLabel() }}</span>
                                <span @class(['pb-1 text-body', 'text-white/80' => $plan->is_featured, 'text-white/55' => ! $plan->is_featured])>/ {{ $plan->periodShort() }}</span>
                            </div>
                            <div @class(['my-6 h-px', 'bg-white/30' => $plan->is_featured, 'bg-white/12' => ! $plan->is_featured])></div>
                            <p class="text-body font-semibold">What's included:</p>
                            <ul class="mt-3 flex flex-1 flex-col gap-2.5">
                                @foreach ($plan->featureList() as $feature)
                                    <li class="flex items-start gap-2.5 text-body @if ($plan->is_featured) text-white/90 @else text-white/70 @endif">
                                        <svg class="mt-0.5 size-[18px] shrink-0 @if ($plan->is_featured) text-white @else text-[#f38c00] @endif" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                            <button type="button" @click="open('subscribe', @js($plan->slug))"
                                    @class([
                                        'mt-7 w-full rounded-[12px] py-4 text-body-lg font-semibold tracking-tight transition',
                                        'bg-white text-[#1e1e1e] hover:bg-white/90' => $plan->is_featured,
                                        'bg-[#f38c00] text-white hover:bg-[#dd7f00]' => ! $plan->is_featured,
                                    ])>Subscribe</button>
                        </div>
                    @endforeach
                    </div>

                    {{-- Right arrow --}}
                    <button type="button" @click="slide(1)" x-show="canRight || canLeft" :disabled="!canRight" aria-label="Next plans"
                            class="absolute -right-3 top-1/2 z-10 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 lg:flex">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>

                {{-- ===================== WHAT WE OFFER (same gymbg section) ===================== --}}
                <div class="grid grid-cols-1 gap-10 pt-20 lg:grid-cols-[1fr_1.05fr] lg:items-center lg:gap-14">
                    <x-img src="{{ cms_image('gym.offer_image') }}" alt="{{ cms('gym.offer_title') }}"
                       sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                       class="h-[320px] w-full rounded-[18px] object-cover sm:h-[460px] lg:h-[640px]" />
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col gap-2">
                        <p class="text-body font-semibold uppercase tracking-[1px] text-[#f38c00]">{{ cms('gym.offer_subtitle') }}</p>
                        <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('gym.offer_title') }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2">
                        @foreach ($offers as $o)
                            <div class="flex flex-col gap-3">
                                <span class="flex size-14 items-center justify-center rounded-2xl bg-[#f38c00]/15 text-[#f38c00]">
                                    @switch($o['icon'])
                                        @case('yoga')
                                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="4.5" r="2"/><path d="M12 7v6M5 9h14M12 13l-4 7M12 13l4 7"/></svg>
                                            @break
                                        @case('trainer')
                                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12h3M19.5 12h3M4.5 9.5v5M19.5 9.5v5M7 12h10M7 8.5v7M17 8.5v7"/></svg>
                                            @break
                                        @case('weight')
                                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 7v5l3 2M21 5l-3 3"/></svg>
                                            @break
                                        @default
                                            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2 5 4-12 2 7h6"/></svg>
                                    @endswitch
                                </span>
                                <h3 class="text-title font-semibold tracking-tight text-white lg:text-xl">{{ $o['title'] }}</h3>
                                <p class="text-body leading-relaxed tracking-tight text-white/65">{{ $o['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                </div>
            </x-layouts.container>
        </section>

        {{-- ============================ MEMBERSHIP POPUP ============================ --}}
        <div x-show="showModal" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/70 p-4 sm:p-6"
             x-transition.opacity @keydown.escape.window="close()">
            <div class="relative my-6 w-full max-w-[1100px] overflow-hidden rounded-[20px] bg-[#1e1e1e] shadow-2xl"
                 @click.outside="close()"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-6 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">

                {{-- Close --}}
                <button type="button" @click="close()" class="absolute right-5 top-5 z-20 flex size-11 items-center justify-center rounded-full border border-white/40 text-white transition hover:bg-white/10">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>

                {{-- ============ STEP: FORM ============ --}}
                <div x-show="step === 'form'" x-cloak class="p-6 sm:p-9 lg:p-11">
                    <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">Gym Membership</h2>

                    {{-- Subscribe / Renewal tabs --}}
                    <div class="mt-6 flex flex-wrap gap-2.5">
                        <button type="button" @click="type = 'subscribe'" :class="type === 'subscribe' ? 'bg-[#f38c00] text-white' : 'bg-[#373d35] text-white/70'" class="rounded-[10px] px-6 py-2.5 text-body font-semibold transition">Subscribe</button>
                        <button type="button" @click="type = 'renewal'" :class="type === 'renewal' ? 'bg-[#f38c00] text-white' : 'bg-[#373d35] text-white/70'" class="rounded-[10px] px-6 py-2.5 text-body font-semibold transition">Renewal</button>
                    </div>

                    {{-- Customer details --}}
                    <div class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#373d35] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Name</span>
                            <input type="text" x-model="name" placeholder="Micheal Philip" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#373d35] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Email</span>
                            <input type="email" x-model="email" placeholder="mich.philip@gmail.com" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#373d35] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Phone Number</span>
                            <div class="flex items-center gap-2">
                                <span class="shrink-0 text-body-lg text-white">🇳🇬 +234</span>
                                <input type="tel" x-model="phone" inputmode="numeric" placeholder="7012623680" class="w-full bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                            </div>
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#373d35] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Date of Birth <span class="text-white/40">(Optional)</span></span>
                            <input type="date" x-model="dob" min="{{ now()->toDateString() }}" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none [&::-webkit-calendar-picker-indicator]:invert">
                        </label>
                    </div>

                    {{-- Plan selection (carousel) --}}
                    <div class="relative mt-8"
                         x-data="{
                            canLeft: false,
                            canRight: true,
                            refresh() { const t = $refs.track; if (!t) return; this.canLeft = t.scrollLeft > 4; this.canRight = (t.scrollLeft + t.clientWidth) < (t.scrollWidth - 4); },
                            slide(dir) { const t = $refs.track; const c = t.querySelector('[data-plan-card]'); const amt = c ? (c.offsetWidth + 16) : 300; t.scrollBy({ left: dir * amt, behavior: 'smooth' }); }
                         }"
                         x-init="$nextTick(() => refresh())"
                         x-effect="if (showModal && step === 'form') $nextTick(() => setTimeout(() => refresh(), 80))">
                        {{-- Left arrow --}}
                        <button type="button" @click="slide(-1)" x-show="canRight || canLeft" :disabled="!canLeft" aria-label="Previous plans"
                                class="absolute -left-3 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 sm:flex">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>

                        {{-- Track --}}
                        <div x-ref="track" @scroll.debounce.50ms="refresh()" class="no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-1">
                            <template x-for="p in plans" :key="p.slug">
                                <button type="button" data-plan-card @click="selectPlan(p.slug)"
                                        class="flex w-[280px] shrink-0 snap-start flex-col rounded-[16px] p-5 text-left transition sm:w-[300px]"
                                        :class="isPlan(p.slug) ? 'bg-[#f38c00] text-white ring-2 ring-[#f38c00]' : 'bg-[#262c22] text-white ring-1 ring-white/10 hover:ring-white/30'">
                                    <span class="text-xl font-semibold tracking-tight" x-text="p.name"></span>
                                    <span class="mt-2 text-body leading-snug" :class="isPlan(p.slug) ? 'text-white/85' : 'text-white/55'" x-text="p.tagline"></span>
                                    <span class="mt-4 flex items-end gap-1">
                                        <span class="text-2xl font-semibold" x-text="p.price_label"></span>
                                        <span class="pb-0.5 text-body" :class="isPlan(p.slug) ? 'text-white/80' : 'text-white/50'" x-text="'/ ' + p.period_short"></span>
                                    </span>
                                    <span class="my-4 h-px" :class="isPlan(p.slug) ? 'bg-white/30' : 'bg-white/10'"></span>
                                    <span class="text-body font-semibold">What's included:</span>
                                    <ul class="mt-2 flex flex-col gap-1.5">
                                        <template x-for="f in p.features" :key="f">
                                            <li class="flex items-start gap-2 text-body-sm" :class="isPlan(p.slug) ? 'text-white/90' : 'text-white/65'">
                                                <svg class="mt-0.5 size-4 shrink-0" :class="isPlan(p.slug) ? 'text-white' : 'text-[#f38c00]'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                                <span x-text="f"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </button>
                            </template>
                        </div>

                        {{-- Right arrow --}}
                        <button type="button" @click="slide(1)" x-show="canRight || canLeft" :disabled="!canRight" aria-label="Next plans"
                                class="absolute -right-3 top-1/2 z-10 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white text-[#ba6d04] shadow-lg transition hover:bg-[#fff3e0] disabled:cursor-not-allowed disabled:opacity-30 sm:flex">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>

                    {{-- Payment options --}}
                    <div class="mt-8 rounded-xl bg-[#373d35] p-5 sm:p-6">
                        <h3 class="text-h3 font-semibold text-white">Payment Options</h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" @click="channel = 'card'" :class="channel === 'card' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-semibold transition">Card</button>
                            <button type="button" @click="channel = 'bank'" :class="channel === 'bank' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Bank</button>
                            <button type="button" @click="channel = 'transfer'" :class="channel === 'transfer' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[50px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Transfer</button>
                        </div>
                        <p class="mt-4 flex items-center gap-2 text-label text-white/60">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/></svg>
                            Card details are entered securely in the Paystack window. We never store your card.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                            <button type="button" @click="pay()" class="flex h-[64px] min-w-[240px] flex-1 items-center justify-center rounded-[10px] bg-[#f38c00] text-body-lg font-semibold text-white transition hover:bg-[#dd7f00] sm:flex-none">Complete Payment</button>
                            <div class="flex flex-col text-white">
                                <span class="text-body font-semibold">Total</span>
                                <span class="text-h3 font-semibold" x-text="selectedPlan ? selectedPlan.price_label + ' / ' + selectedPlan.period_short : '—'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ STEP: SUCCESS ============ --}}
                <div x-show="step === 'success'" x-cloak class="px-6 pb-12 pt-14 text-center sm:px-10 lg:px-16 lg:pb-16 lg:pt-16">
                    <h2 class="text-left text-2xl font-semibold tracking-tight text-white lg:text-h2">Gym Membership</h2>
                    <div class="mx-auto mt-8 flex max-w-[560px] flex-col items-center gap-5">
                        <img src="{{ asset('images/checkcircle.png') }}" alt="Success" class="size-[90px] object-contain lg:size-[110px]">
                        <div class="flex flex-col gap-1">
                            <h3 class="text-h3 font-bold tracking-tight text-[#f38c00] lg:text-h2">Subscription Successful!</h3>
                            <p class="text-body text-white/70">Your subscription is now active.</p>
                        </div>
                        <div class="mt-2 flex flex-col items-center gap-1">
                            <span class="text-label uppercase tracking-wide text-white/50">Membership ID</span>
                            <span class="text-3xl font-bold tracking-tight text-white lg:text-[40px]" x-text="success ? success.code : ''"></span>
                        </div>
                        <div class="mt-3 flex w-full flex-col gap-2 border-t border-white/10 pt-5 text-body text-white/80">
                            <p class="font-medium text-white">Customer Details</p>
                            <p class="flex justify-between gap-3"><span class="text-white/55">Name</span><span x-text="success ? success.customer_name : ''"></span></p>
                            <p class="flex justify-between gap-3"><span class="text-white/55">Plan</span><span x-text="success ? success.plan_name : ''"></span></p>
                            <p class="flex justify-between gap-3"><span class="text-white/55">Contact number</span><span x-text="success ? (success.customer_phone || '—') : ''"></span></p>
                            <p class="flex justify-between gap-3"><span class="text-white/55">Email Address</span><span class="break-all" x-text="success ? success.customer_email : ''"></span></p>
                            <p class="flex justify-between gap-3"><span class="text-white/55">Valid till</span><span x-text="success ? success.ends_at : ''"></span></p>
                        </div>
                        <div class="mt-4 flex w-full flex-col gap-3">
                            <button type="button" onclick="window.print()" class="flex h-[64px] w-full items-center justify-center gap-2.5 rounded-[10px] bg-[#f38c00] text-body-lg font-semibold text-white transition hover:bg-[#dd7f00]">
                                Download Receipt
                                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                            </button>
                            <a href="{{ route('home') }}" wire:navigate class="flex h-[64px] w-full items-center justify-center rounded-[10px] border border-white/60 text-body-lg font-medium text-white transition hover:bg-white/10">Back to Homepage</a>
                        </div>
                    </div>
                </div>

                {{-- Hidden POST form submitted after a successful Paystack charge --}}
                <form x-ref="form" method="POST" action="{{ route('gym.subscribe') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="reference" :value="payReference">
                    <input type="hidden" name="plan" :value="planSlug">
                    <input type="hidden" name="type" :value="type">
                    <input type="hidden" name="name" :value="name">
                    <input type="hidden" name="email" :value="email">
                    <input type="hidden" name="phone" :value="phone">
                    <input type="hidden" name="dob" :value="dob">
                    <input type="hidden" name="channel" :value="channel">
                </form>
            </div>
        </div>

    </div>{{-- /gymMembership --}}

    @if (! empty($paystackKey))
        @push('scripts')
            <script src="https://js.paystack.co/v1/inline.js"></script>
        @endpush
    @endif
</x-layouts.web>
