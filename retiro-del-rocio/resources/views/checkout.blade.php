<x-layouts.web title="Checkout — Retiro Del Rocio">
    <section class="w-full bg-gradient-to-b from-[#222a1f] to-[#1e1e1e] py-10 lg:py-14"
             x-data="{
                firstName: '',
                lastName: '',
                email: '',
                phone: '',
                channel: 'card',
                remember: false,
                pay() {
                    if (!this.firstName || !this.lastName || !this.email) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Please enter your name and email to continue.' } }));
                        return;
                    }
                    @if (empty($paystackKey))
                        window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Payments are not configured yet. Please add your Paystack keys.' } }));
                        return;
                    @else
                        if (typeof PaystackPop === 'undefined') {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Payment library failed to load. Check your connection and try again.' } }));
                            return;
                        }
                        const channelMap = { card: 'card', bank: 'bank', transfer: 'bank_transfer' };
                        const handler = PaystackPop.setup({
                            key: @js($paystackKey),
                            email: this.email,
                            amount: {{ (int) $booking['total_kobo'] }},
                            currency: 'NGN',
                            channels: [channelMap[this.channel] || 'card'],
                            metadata: {
                                name: (this.firstName + ' ' + this.lastName).trim(),
                                phone: this.phone ? '+234' + this.phone : '',
                                custom_fields: [
                                    { display_name: 'Room', variable_name: 'room', value: @js($booking['room']) },
                                    { display_name: 'Check-in / Check-out', variable_name: 'stay', value: @js($booking['date_range']) },
                                ],
                            },
                            callback: function (response) {
                                window.location.href = '{{ route('checkout.callback') }}?reference=' + response.reference;
                            },
                            onClose: function () {
                                window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', message: 'Payment window closed before completing your reservation.' } }));
                            },
                        });
                        handler.openIframe();
                    @endif
                },
             }">
        <x-layouts.container class="flex flex-col gap-8">
            {{-- Breadcrumb --}}
            <a href="{{ ! empty($booking['room_slug']) ? route('rooms.show', $booking['room_slug']) : route('rooms') }}" wire:navigate class="flex w-fit items-center gap-2 text-body font-semibold tracking-tight text-white transition hover:text-[#f38c00] sm:text-title">
                <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span>Home / Room &amp; Apartment / Pandora's_suite / <span class="text-[#f38c00]">Checkout</span></span>
            </a>

            {{-- Heading --}}
            <h1 class="max-w-[687px] text-3xl font-semibold leading-tight tracking-tight text-white sm:text-4xl lg:text-h1 lg:leading-[52px]">
                Book your reservation securely in less 2 minutes.
            </h1>

            {{-- Two columns --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_578px]">

                {{-- ============ LEFT: image + summary ============ --}}
                <div class="flex flex-col gap-1.5">
                    {{-- Room image --}}
                    <div class="relative overflow-hidden rounded-[10px]">
                        <x-img src="{{ str_replace(' ', '%20', asset('images/image 7.jpg')) }}" alt="{{ $booking['room'] }}"
                               sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                               class="h-[320px] w-full object-cover sm:h-[440px] lg:h-[560px]" />
                        <div class="absolute inset-0 bg-gradient-to-b from-black/75 via-transparent to-black/30"></div>
                    </div>

                    {{-- Summary --}}
                    <div class="flex flex-col gap-8 rounded-[10px] bg-[#373d35]/[0.34] p-6 sm:p-8 lg:p-10">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="text-3xl font-semibold tracking-tight text-white lg:text-h2">{{ cms('checkout.summary_title') }}</h2>
                            <a href="{{ ! empty($booking['room_slug']) ? route('rooms.show', $booking['room_slug']) : route('rooms') }}" wire:navigate class="flex shrink-0 items-center gap-2 text-body font-semibold tracking-tight text-white transition hover:text-[#f38c00] lg:text-body-lg">
                                {{ cms('checkout.edit_label') }}
                                <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </a>
                        </div>

                        {{-- Detail rows --}}
                        <div class="flex flex-col text-body-sm font-semibold text-[#f5f5f5] lg:text-body-lg">
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M21 10.78V8a2 2 0 0 0-2-2h-5v5h-4V6H5a2 2 0 0 0-2 2v2.78A2 2 0 0 0 2 12.5V19h2v-2h16v2h2v-6.5a2 2 0 0 0-1-1.72z"/></svg>
                                    Room/Apartment
                                </span>
                                <span>{{ $booking['room'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4a3 3 0 0 1 0 6M21 20c0-2.5-1.5-4.6-3.6-5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Guest
                                </span>
                                <span>{{ $booking['guests'] }} Guest</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zM5 9h14v10H5V9z"/></svg>
                                    Check-in / Check-out
                                </span>
                                <span>{{ $booking['date_range'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0-6 6c0 4 6 12 6 12s6-8 6-12a6 6 0 0 0-6-6z"/><circle cx="12" cy="9" r="2.5"/></svg>
                                    Stay Duration
                                </span>
                                <span>{{ $booking['nights'] }} {{ \Illuminate\Support\Str::plural('Night', $booking['nights']) }}</span>
                            </div>
                            @if ($booking['pickup_vehicle'])
                                <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                    <span class="flex items-center gap-2">
                                        <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
                                        Vehicle Pickup
                                    </span>
                                    <span>{{ $booking['pickup_vehicle'] }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Price breakdown --}}
                        <div class="flex flex-col border-t-2 border-white/15 pt-2 text-white">
                            <div class="flex items-start justify-between gap-4 py-4">
                                <div class="flex flex-col">
                                    <span class="text-body-lg font-semibold tracking-tight lg:text-h3">{{ $booking['room'] }}</span>
                                    <span class="text-body-sm text-white/60 lg:text-body-sm">{{ number_format($booking['price_per_night']) }} &times; {{ $booking['nights'] }} {{ \Illuminate\Support\Str::plural('night', $booking['nights']) }}</span>
                                </div>
                                <span class="text-body-lg font-semibold tracking-tight lg:text-h3">{{ $booking['room_subtotal_label'] }}</span>
                            </div>
                            @if ($booking['pickup_price'])
                                <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                    <span class="text-body-lg font-medium tracking-tight lg:text-body-lg">Vehicle Pickup</span>
                                    <span class="text-body-lg font-semibold tracking-tight lg:text-body-lg">{{ $booking['pickup_price'] }}</span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="text-body-lg font-semibold tracking-tight lg:text-h3">VAT 7.5%</span>
                                <span class="text-body-lg font-semibold tracking-tight lg:text-h3">{{ $booking['vat_label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="text-body-lg font-semibold tracking-tight lg:text-h3">Fees</span>
                                <span class="text-body-lg font-semibold tracking-tight lg:text-h3">{{ $booking['fees_label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="text-title font-semibold tracking-tight lg:text-h3">Total</span>
                                <span class="text-h3 font-semibold tracking-tight lg:text-h2">{{ $booking['total_label'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ RIGHT: details + payment ============ --}}
                <div class="flex flex-col gap-[21px]">
                    {{-- Who's checking in --}}
                    <div class="rounded-[10px] bg-[#373d35] p-6 sm:p-8">
                        <h2 class="text-h3 font-semibold tracking-tight text-white">{{ cms('checkout.guest_title') }}</h2>
                        <div class="mt-6 flex flex-col gap-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2">
                                    <span class="text-label font-medium tracking-tight text-[#a5a5a5]">First Name</span>
                                    <input type="text" x-model="firstName" placeholder="Micheal"
                                           class="bg-transparent text-body-lg tracking-tight text-white placeholder:text-white/40 focus:outline-none">
                                </label>
                                <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2">
                                    <span class="text-label font-medium tracking-tight text-[#a5a5a5]">Last Name</span>
                                    <input type="text" x-model="lastName" placeholder="Philips"
                                           class="bg-transparent text-body-lg tracking-tight text-white placeholder:text-white/40 focus:outline-none">
                                </label>
                            </div>
                            <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2">
                                <span class="text-label font-medium tracking-tight text-[#a5a5a5]">Email Address</span>
                                <input type="email" x-model="email" placeholder="micheal.philips@gmail.com"
                                       class="bg-transparent text-body-lg tracking-tight text-white placeholder:text-white/40 focus:outline-none">
                            </label>
                            <label class="flex flex-col gap-1.5 border-b border-white/30 pb-2">
                                <span class="text-label font-medium tracking-tight text-[#a5a5a5]">Phone Number</span>
                                <div class="flex items-center gap-2">
                                    <span class="flex shrink-0 items-center gap-1 text-body-lg text-white">
                                        <img loading="lazy" src="{{ str_replace(' ', '%20', asset('images/Hotel Logo 1.png')) }}" alt="" class="hidden">
                                        🇳🇬 +234
                                    </span>
                                    <input type="tel" x-model="phone" placeholder="8143432903" inputmode="numeric"
                                           class="w-full bg-transparent text-body-lg tracking-tight text-white placeholder:text-white/40 focus:outline-none">
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Cancellation policy --}}
                    <div class="rounded-[10px] bg-[rgba(113,113,113,0.27)] p-6 sm:p-8">
                        <h3 class="text-title font-semibold tracking-tight text-white lg:text-h3">Cancellation Policy</h3>
                        <p class="mt-5 text-body leading-snug tracking-tight text-[#dadbda] lg:text-body-lg">
                            Set in a beautifully restored colonial building, sofitel Legend Santa offers luxurious blend of modern amenities blend of modern amenities blend of modern amenities blend of modern amenities
                        </p>
                        <a href="#" class="mt-1 inline-block text-body text-[#368aff] hover:underline lg:text-body-lg">Read more</a>
                    </div>

                    {{-- Payment options --}}
                    <div class="rounded-[10px] bg-[#373d35] p-6 sm:p-8">
                        <h2 class="text-h3 font-semibold tracking-tight text-white">Payment Options</h2>

                        {{-- Channel tabs --}}
                        <div class="mt-6 flex flex-wrap gap-2">
                            <button type="button" @click="channel = 'card'"
                                    :class="channel === 'card' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'"
                                    class="flex h-[56px] items-center gap-2 rounded-[13px] px-[22px] text-body-lg font-semibold transition">
                                <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20" stroke-linecap="round"/></svg>
                                Card
                            </button>
                            <button type="button" @click="channel = 'bank'"
                                    :class="channel === 'bank' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'"
                                    class="flex h-[56px] items-center gap-2 rounded-[13px] px-[18px] text-body-lg font-medium transition">
                                <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 2 7v2h20V7L12 2zM4 11v7H2v2h20v-2h-2v-7h-2v7h-3v-7h-2v7H8v-7H6v7H4v-7z"/></svg>
                                Bank
                            </button>
                            <button type="button" @click="channel = 'transfer'"
                                    :class="channel === 'transfer' ? 'bg-[#ba6d04] text-white' : 'bg-[#696969] text-[#c9c9c9]'"
                                    class="flex h-[56px] items-center gap-2 rounded-[13px] px-[16px] text-body-lg font-medium transition">
                                <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13l-3-3M20 16H7l3 3"/></svg>
                                Transfer
                            </button>
                        </div>

                        {{-- Secure note (card entry happens securely in the Paystack window — matches the spa checkout) --}}
                        <p class="mt-6 flex items-center gap-2 text-label text-white/60">
                            <svg class="icon-xs shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/></svg>
                            {{ cms('checkout.secure_note') }}
                        </p>

                        {{-- Submit + total --}}
                        <div class="mt-9 flex flex-wrap items-center gap-7">
                            <button type="button" @click="pay()"
                                    class="flex h-[75px] min-w-[240px] flex-1 items-center justify-center rounded-[6px] bg-[#ba6d04] text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03] sm:flex-none sm:w-[279px]">
                                {{ cms('checkout.pay_label') }}
                            </button>
                            <div class="flex flex-col text-white">
                                <span class="text-body-lg font-semibold tracking-tight">Total</span>
                                <span class="text-h3 font-semibold tracking-tight lg:text-h2">{{ $booking['total_label'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-layouts.container>
    </section>

    @if (! empty($paystackKey))
        @push('scripts')
            <script src="https://js.paystack.co/v1/inline.js"></script>
        @endpush
    @endif
</x-layouts.web>
