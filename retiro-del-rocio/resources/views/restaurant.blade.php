<x-layouts.web title="Restaurant — Retiro Del Rocio"
    description="Experience fine dining at Retiro Del Rocio. Reserve a table or lounge — exceptional cuisine, elegant ambiance and attentive service in Jos.">

    @php
        $tables = \App\Models\RestaurantTable::active()->ordered()->get();
        $tablesJson = $tables->map(fn ($t) => [
            'id' => $t->id, 'name' => $t->name, 'area' => $t->area,
            'capacity' => $t->capacity, 'shape' => $t->shape,
            'capacity_label' => $t->capacityLabel(),
        ])->values();

        $restoSuccess = session('restaurant_success');
        session()->forget('restaurant_success'); // consume once

        $paystackKey = config('services.paystack.public_key');
        $fee = (int) preg_replace('/[^0-9]/', '', cms('restaurant.reservation_fee')) ?: 10000;

        $dishes = [
            ['image' => cms_image('restaurant.dish_1_image'), 'title' => cms('restaurant.dish_1_title'), 'text' => cms('restaurant.dish_1_text')],
            ['image' => cms_image('restaurant.dish_2_image'), 'title' => cms('restaurant.dish_2_title'), 'text' => cms('restaurant.dish_2_text')],
            ['image' => cms_image('restaurant.dish_3_image'), 'title' => cms('restaurant.dish_3_title'), 'text' => cms('restaurant.dish_3_text')],
        ];

        $hours = [
            ['icon' => 'breakfast', 'label' => 'Breakfast', 'time' => cms('restaurant.breakfast_hours')],
            ['icon' => 'lunch', 'label' => 'Lunch', 'time' => cms('restaurant.lunch_hours')],
            ['icon' => 'dinner', 'label' => 'Dinner', 'time' => cms('restaurant.dinner_hours')],
        ];

        $occasions = ['Casual Dining', 'Birthday', 'Anniversary', 'Business Meeting', 'Date Night', 'Celebration', 'Other'];
    @endphp

    <div x-data="restaurantReservation({
            tables: @js($tablesJson),
            paystackKey: @js($paystackKey),
            fee: @js($fee),
            successData: @js($restoSuccess),
         })"
         class="bg-[#161310]">

        {{-- ============================ HERO ============================ --}}
        <section class="relative w-full">
            <x-img src="{{ cms_image('restaurant.hero_image') }}" alt="Restaurant" sizes="100vw"
                   loading="eager" fetchpriority="high"
                   class="h-[460px] w-full object-cover sm:h-[560px] lg:h-[660px]" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/15"></div>
            <x-layouts.container class="absolute inset-0 flex items-center">
                <div class="flex max-w-[680px] flex-col gap-6">
                    <h1 class="text-4xl font-semibold leading-tight tracking-tight text-white sm:text-5xl lg:text-display lg:leading-[1.05]">{{ cms('restaurant.hero_title') }}</h1>
                    <p class="max-w-[600px] text-lg leading-relaxed tracking-tight text-white/90 lg:text-body-lg">{{ cms('restaurant.hero_text') }}</p>
                    <div>
                        <button type="button" @click="open()"
                                class="inline-flex items-center gap-2.5 rounded-[12px] bg-[#f38c00] px-9 py-4 text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#dd7f00]">
                            {{ cms('restaurant.reserve_label') }}
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </x-layouts.container>
        </section>

        {{-- ===================== DARK SECTION (linear bg) ===================== --}}
        <section class="relative w-full bg-gradient-to-b from-[#1f1a13] via-black to-[#161310] pb-20 lg:pb-28">
            <x-layouts.container class="flex flex-col">

                {{-- Opening hours card (overlaps the hero on larger screens; sits
                     below it with a gap on mobile so it never touches the hero button) --}}
                <div class="mt-10 rounded-[22px] border border-white/10 bg-[#20211c]/95 px-6 py-10 shadow-2xl backdrop-blur sm:-mt-20 lg:px-12 lg:py-12">
                    <div class="mx-auto flex max-w-[760px] flex-col items-center gap-2 text-center">
                        <h2 class="text-3xl font-semibold tracking-tight text-white lg:text-h2">{{ cms('restaurant.hours_title') }}</h2>
                        <p class="text-body leading-relaxed text-white/65 lg:text-body-lg">{{ cms('restaurant.hours_text') }}</p>
                    </div>
                    <div class="mt-9 grid grid-cols-1 gap-8 sm:grid-cols-3">
                        @foreach ($hours as $h)
                            <div class="flex flex-col items-center gap-3 text-center">
                                <img src="{{ asset('images/'.$h['icon'].'.png') }}" alt="{{ $h['label'] }}" class="size-16 object-contain lg:size-20" loading="lazy">
                                <p class="text-title font-semibold tracking-tight text-white">{{ $h['label'] }}</p>
                                <p class="text-body text-[#f38c00]">{{ $h['time'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Signature dishes --}}
                <div class="mt-20 flex flex-col items-center gap-3 text-center lg:mt-28">
                    <h2 class="max-w-[820px] text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('restaurant.dishes_title') }}</h2>
                    <p class="max-w-[820px] text-body leading-relaxed text-white/65 lg:text-body-lg">{{ cms('restaurant.dishes_text') }}</p>
                </div>
                <div class="mt-12 grid grid-cols-1 gap-7 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($dishes as $dish)
                        <div class="flex flex-col overflow-hidden rounded-[18px] border border-white/10 bg-[#20211c]">
                            <x-img src="{{ $dish['image'] }}" alt="{{ $dish['title'] }}" sizes="(min-width:1024px) 33vw, 100vw"
                                   loading="lazy" decoding="async" class="h-[260px] w-full object-cover lg:h-[300px]" />
                            <div class="flex flex-col gap-3 p-7">
                                <h3 class="text-2xl font-semibold tracking-tight text-white">{{ $dish['title'] }}</h3>
                                <p class="text-body leading-relaxed text-white/60">{{ $dish['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Culinary excellence --}}
                <div class="mt-24 flex flex-col gap-8 lg:mt-32">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center lg:gap-14">
                        <p class="order-2 text-body leading-relaxed text-white/65 lg:order-1 lg:text-body-lg">{{ cms('restaurant.culinary_text') }}</p>
                        <h2 class="order-1 text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:order-2 lg:text-h1">{{ cms('restaurant.culinary_title') }}</h2>
                    </div>
                    <x-img src="{{ cms_image('restaurant.culinary_image') }}" alt="{{ cms('restaurant.culinary_title') }}"
                           sizes="100vw" loading="lazy" decoding="async"
                           class="h-[300px] w-full rounded-[20px] object-cover sm:h-[460px] lg:h-[620px]" />
                </div>

                {{-- More than a dining destination --}}
                <div class="mt-24 flex flex-col gap-8 lg:mt-32">
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center lg:gap-14">
                        <h2 class="text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:text-h1">{{ cms('restaurant.dining_title') }}</h2>
                        <p class="text-body leading-relaxed text-white/65 lg:text-body-lg">{{ cms('restaurant.dining_text') }}</p>
                    </div>
                    <x-img src="{{ cms_image('restaurant.dining_image') }}" alt="{{ cms('restaurant.dining_title') }}"
                           sizes="100vw" loading="lazy" decoding="async"
                           class="h-[300px] w-full rounded-[20px] object-cover sm:h-[460px] lg:h-[620px]" />
                </div>

            </x-layouts.container>
        </section>

        {{-- ============================ RESERVATION POPUP ============================ --}}
        <div x-show="showModal" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/75 p-4 sm:p-6"
             x-transition.opacity @keydown.escape.window="close()">
            <div class="relative my-6 w-full max-w-[1080px] overflow-hidden rounded-[20px] bg-[#1b1b18] shadow-2xl"
                 @click.outside="close()"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-6 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">

                {{-- Close --}}
                <button type="button" @click="close()" class="absolute right-5 top-5 z-20 flex size-11 items-center justify-center rounded-full border border-white/40 text-white transition hover:bg-white/10">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>

                {{-- ============ STEP: RESERVE ============ --}}
                <div x-show="step === 'reserve'" x-cloak class="p-6 sm:p-9 lg:p-11">
                    <h2 class="text-3xl font-semibold tracking-tight text-white lg:text-h1">{{ cms('restaurant.reservation_title') }}</h2>
                    <p class="mt-3 max-w-[760px] text-body text-white/60">{{ cms('restaurant.reservation_text') }}</p>

                    {{-- Table / Lounge tabs --}}
                    <div class="mt-6 flex flex-wrap gap-2.5">
                        <button type="button" @click="setArea('dining')" :class="area === 'dining' ? 'bg-[#f38c00] text-white' : 'bg-[#33332d] text-white/70'" class="rounded-[10px] px-6 py-2.5 text-body font-semibold transition">Table Reservation</button>
                        <button type="button" @click="setArea('lounge')" :class="area === 'lounge' ? 'bg-[#f38c00] text-white' : 'bg-[#33332d] text-white/70'" class="rounded-[10px] px-6 py-2.5 text-body font-semibold transition">Lounge</button>
                    </div>

                    {{-- Fields --}}
                    <div class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Occasion</span>
                            <select x-model="occasion" class="bg-transparent text-body-lg text-white focus:outline-none [&>option]:text-black">
                                @foreach ($occasions as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Number of Guest</span>
                            <select x-model.number="guests" class="bg-transparent text-body-lg text-white focus:outline-none [&>option]:text-black">
                                @for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}">{{ $i }} {{ \Illuminate\Support\Str::plural('Guest', $i) }}</option>@endfor
                            </select>
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Date</span>
                            <input type="date" x-model="date" min="{{ now()->toDateString() }}" class="bg-transparent text-body-lg text-white focus:outline-none [&::-webkit-calendar-picker-indicator]:invert">
                        </label>
                        <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                            <span class="text-label font-medium text-[#a5a5a5]">Time</span>
                            <input type="time" x-model="time" class="bg-transparent text-body-lg text-white focus:outline-none [&::-webkit-calendar-picker-indicator]:invert">
                        </label>
                    </div>

                    {{-- Preferred table --}}
                    <div class="mt-8">
                        <p class="text-body font-semibold text-white" x-text="area === 'lounge' ? 'Select Preferred Lounge' : 'Select Preferred Table'"></p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <template x-for="t in areaTables" :key="t.id">
                                <button type="button" @click="selectTable(t.id)"
                                        class="flex w-[120px] flex-col items-center gap-2 rounded-[14px] border p-4 transition"
                                        :class="isTable(t.id) ? 'border-[#f38c00] bg-[#f38c00]/15' : 'border-white/10 bg-[#26261f] hover:border-white/30'">
                                    <svg class="size-10" :class="isTable(t.id) ? 'text-[#f38c00]' : 'text-white/55'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="7" y="7" width="10" height="10" rx="2"/>
                                        <path d="M10 4h4M10 20h4M4 10v4M20 10v4"/>
                                    </svg>
                                    <span class="text-label font-medium text-white/85" x-text="t.capacity_label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Special request --}}
                    <label class="mt-7 flex flex-col gap-2 rounded-xl bg-[#33332d] px-4 py-3">
                        <span class="text-label font-medium text-[#a5a5a5]">Special Request <span class="text-white/40">(Optional)</span></span>
                        <textarea x-model="specialRequest" rows="2" placeholder="Please let us know if you have any special request, dietary restrictions, or table preferences." class="resize-none bg-transparent text-body text-white placeholder:text-white/40 focus:outline-none"></textarea>
                    </label>

                    {{-- Reservation summary --}}
                    <h3 class="mt-9 text-h3 font-semibold tracking-tight text-white">Reservation Summary</h3>
                    <p class="mt-2 max-w-[760px] text-body leading-relaxed text-white/55">{{ cms('restaurant.summary_text') }}</p>
                    <div class="mt-4 flex flex-col gap-6 rounded-[16px] border border-white/10 bg-[#26261f] p-6 sm:flex-row sm:items-center">
                        <div class="flex size-[120px] shrink-0 items-center justify-center rounded-[16px] bg-[#f38c00]">
                            <svg class="size-16 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 4h4M10 20h4M4 10v4M20 10v4"/></svg>
                        </div>
                        <div class="flex flex-1 flex-col gap-3">
                            <p class="text-title font-semibold text-white">Details of Reservation</p>
                            <div class="flex flex-col gap-2 text-body text-white/80">
                                <p class="flex items-center gap-2"><svg class="size-4 text-[#f38c00]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M5 8h14l-1 12H6z" stroke-linecap="round" stroke-linejoin="round"/></svg><span x-text="occasion + ' · ' + (area === 'lounge' ? 'Lounge' : 'Table Reservation')"></span></p>
                                <p class="flex items-center gap-2"><svg class="size-4 text-[#f38c00]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" stroke-linecap="round" stroke-linejoin="round"/></svg><span x-text="guests + ' Guest' + (guests > 1 ? 's' : '')"></span><span x-show="selectedTable" class="text-white/45" x-text="selectedTable ? '· ' + selectedTable.name : ''"></span></p>
                                <p class="flex items-center gap-2"><svg class="size-4 text-[#f38c00]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke-linecap="round"/></svg><span x-text="prettyDate"></span><span class="text-white/45" x-text="time ? '· ' + prettyTime : ''"></span></p>
                            </div>
                            <button type="button" @click="goCheckout()" class="mt-2 w-full rounded-[10px] bg-[#f38c00] py-3.5 text-body-lg font-semibold text-white transition hover:bg-[#dd7f00] sm:w-[200px]">Reserve</button>
                        </div>
                    </div>
                </div>

                {{-- ============ STEP: CHECKOUT ============ --}}
                <div x-show="step === 'checkout'" x-cloak class="p-6 sm:p-9 lg:p-11">
                    <h2 class="text-3xl font-semibold tracking-tight text-white lg:text-h2">{{ cms('restaurant.reservation_title') }}</h2>

                    <div class="mt-7 grid grid-cols-1 gap-8 lg:grid-cols-[1fr_1.1fr]">
                        {{-- Order details --}}
                        <div class="flex flex-col gap-5">
                            <div class="flex items-center justify-center rounded-[16px] bg-[#f38c00] py-9">
                                <svg class="size-24 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 4h4M10 20h4M4 10v4M20 10v4"/></svg>
                            </div>
                            <div class="rounded-[16px] border border-white/10 bg-[#26261f] p-6">
                                <p class="text-title font-semibold text-white">Order Details</p>
                                <p class="mt-1 text-label uppercase tracking-wide text-white/40">Reservation Details</p>
                                <div class="mt-4 flex flex-col gap-2.5 text-body text-white/80">
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Reservation Type</span><span x-text="area === 'lounge' ? 'Lounge' : 'Table Reservation'"></span></p>
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Occasion</span><span x-text="occasion"></span></p>
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Number of Guest</span><span x-text="guests + ' Guest' + (guests > 1 ? 's' : '')"></span></p>
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Table</span><span x-text="selectedTable ? selectedTable.name : '—'"></span></p>
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Date</span><span x-text="prettyDate"></span></p>
                                    <p class="flex justify-between gap-3"><span class="text-white/50">Time</span><span x-text="prettyTime"></span></p>
                                </div>
                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-4">
                                    <span class="text-body text-white/60">Refundable reservation fee</span>
                                    <span class="text-h3 font-semibold text-white" x-text="money(fee)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Customer details + payment --}}
                        <div class="flex flex-col gap-5">
                            <div class="flex flex-col gap-4">
                                <p class="text-title font-semibold text-white">Customer Details</p>
                                <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                                    <span class="text-label font-medium text-[#a5a5a5]">Full Name</span>
                                    <input type="text" x-model="name" placeholder="Micheal Philip" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                                </label>
                                <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                                    <span class="text-label font-medium text-[#a5a5a5]">Email Address</span>
                                    <input type="email" x-model="email" placeholder="mich.philip@gmail.com" class="bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                                </label>
                                <label class="flex flex-col gap-1.5 rounded-xl bg-[#33332d] px-4 py-3">
                                    <span class="text-label font-medium text-[#a5a5a5]">Phone Number</span>
                                    <div class="flex items-center gap-2">
                                        <span class="shrink-0 text-body-lg text-white">🇳🇬 +234</span>
                                        <input type="tel" x-model="phone" inputmode="numeric" placeholder="7012623680" class="w-full bg-transparent text-body-lg text-white placeholder:text-white/40 focus:outline-none">
                                    </div>
                                </label>
                            </div>

                            <div class="rounded-xl border border-white/10 bg-[#26261f] p-4">
                                <p class="text-body font-semibold text-white">Cancellation Policy</p>
                                <p class="mt-1.5 text-label leading-relaxed text-white/55">{{ cms('restaurant.cancellation_policy') }}</p>
                            </div>

                            <div>
                                <p class="text-body font-semibold text-white">Payment Options</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" @click="channel = 'card'" :class="channel === 'card' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[46px] items-center gap-2 rounded-[11px] px-5 text-body font-semibold transition">Card</button>
                                    <button type="button" @click="channel = 'bank'" :class="channel === 'bank' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[46px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Bank</button>
                                    <button type="button" @click="channel = 'transfer'" :class="channel === 'transfer' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'" class="flex h-[46px] items-center gap-2 rounded-[11px] px-5 text-body font-medium transition">Transfer</button>
                                </div>
                                <p class="mt-3 flex items-center gap-2 text-label text-white/55">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/></svg>
                                    Card details are entered securely in the Paystack window. We never store your card.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" @click="pay()" class="flex h-[58px] flex-1 items-center justify-center rounded-[10px] bg-[#f38c00] text-body-lg font-semibold text-white transition hover:bg-[#dd7f00]">Confirm &amp; Pay <span class="ml-1.5" x-text="money(fee)"></span></button>
                                <button type="button" @click="backToReserve()" class="h-[58px] rounded-[10px] border border-white/30 px-6 text-body font-medium text-white transition hover:bg-white/10">Back</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ STEP: SUCCESS ============ (mirrors the spa & wellness success layout) --}}
                <div x-show="step === 'success'" x-cloak class="px-6 pb-10 pt-16 sm:px-10 sm:pb-12 sm:pt-20 lg:px-14 lg:pb-14 lg:pt-24">
                    <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-[1fr_441px] lg:gap-[78px]">
                        {{-- Left: Order Details --}}
                        <div class="flex flex-col gap-7 text-white">
                            <p class="text-2xl font-semibold tracking-tight lg:text-h3">Order Details</p>

                            <div class="flex flex-col gap-2.5 text-body lg:text-body-lg">
                                <p class="font-medium text-[#f38c00]">Reservation Details</p>
                                <div class="flex items-center justify-between"><span>Reservation Type:</span><span class="font-medium" x-text="success ? success.occasion : ''"></span></div>
                                <div class="flex items-center justify-between"><span>Number of Guest:</span><span class="font-medium" x-text="success ? success.guests_label : ''"></span></div>
                                <div class="flex items-center justify-between"><span>Date:</span><span class="font-medium" x-text="success ? success.date : ''"></span></div>
                                <div class="flex items-center justify-between"><span>Time:</span><span class="font-medium" x-text="success ? success.time : ''"></span></div>
                            </div>

                            <div class="flex items-center justify-between border-t border-white/15 pt-5">
                                <span class="text-body-lg font-medium text-[#f38c00]">Refundable reservation fee</span>
                                <span class="text-h3 font-semibold tracking-tight lg:text-h2" x-text="success ? success.fee_label : ''"></span>
                            </div>
                        </div>

                        {{-- Right: success + ID + customer + actions --}}
                        <div class="flex flex-col items-center gap-6 text-center">
                            <img loading="lazy" src="{{ asset('images/checkcircle.png') }}" alt="Success" class="size-[120px] object-contain lg:size-[150px]">
                            <div class="flex flex-col gap-1.5">
                                <h2 class="text-h3 font-bold tracking-tight text-[#f38c00] lg:text-h2">{{ cms('restaurant.success_title') }}</h2>
                                <p class="text-body text-white/80">{{ cms('restaurant.success_text') }}</p>
                            </div>

                            <div class="flex flex-col items-center gap-1">
                                <span class="text-label uppercase tracking-wide text-white/50">Reservation ID</span>
                                <span class="text-2xl font-bold tracking-tight text-white lg:text-3xl" x-text="success ? success.code : ''"></span>
                            </div>

                            <div class="flex w-full flex-col gap-2 border-t border-white/15 pt-5 text-body text-white/80">
                                <p class="font-medium text-white">Customer Details</p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Name</span><span x-text="success ? success.customer_name : ''"></span></p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Contact number</span><span x-text="success ? (success.customer_phone || '—') : ''"></span></p>
                                <p class="flex justify-between gap-3"><span class="text-white/55">Email Address</span><span class="break-all" x-text="success ? success.customer_email : ''"></span></p>
                            </div>

                            <div class="flex w-full flex-col gap-5">
                                <button type="button" onclick="window.print()" class="flex h-[70px] w-full items-center justify-center gap-2.5 rounded-[6px] bg-[#ba6d04] text-body-lg font-semibold text-white transition hover:bg-[#a35f03]">
                                    Download Receipt
                                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                                </button>
                                <a href="{{ route('home') }}" wire:navigate class="flex h-[70px] w-full items-center justify-center rounded-[6px] border border-white text-body-lg font-medium text-white transition hover:bg-white/10">Back to Homepage</a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Hidden POST form submitted after a successful Paystack charge --}}
                <form x-ref="form" method="POST" action="{{ route('restaurant.reserve') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="reference" :value="payReference">
                    <input type="hidden" name="area" :value="area">
                    <input type="hidden" name="table_id" :value="tableId">
                    <input type="hidden" name="occasion" :value="occasion">
                    <input type="hidden" name="guests" :value="guests">
                    <input type="hidden" name="date" :value="date">
                    <input type="hidden" name="time" :value="time">
                    <input type="hidden" name="special_request" :value="specialRequest">
                    <input type="hidden" name="name" :value="name">
                    <input type="hidden" name="email" :value="email">
                    <input type="hidden" name="phone" :value="phone">
                    <input type="hidden" name="channel" :value="channel">
                </form>
            </div>
        </div>

    </div>{{-- /restaurantReservation --}}

    @if (! empty($paystackKey))
        @push('scripts')
            <script src="https://js.paystack.co/v1/inline.js"></script>
        @endpush
    @endif
</x-layouts.web>
