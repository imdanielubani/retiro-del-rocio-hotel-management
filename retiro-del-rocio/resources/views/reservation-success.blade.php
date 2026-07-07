<x-layouts.web title="Reservation Successful — Retiro Del Rocio">
    <section class="w-full bg-gradient-to-b from-[#222a1f] to-[#1e1e1e] py-10 lg:py-14">
        <x-layouts.container>
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-[785px_1fr] lg:gap-16">

                {{-- ============ LEFT: image + order details ============ --}}
                <div class="flex flex-col gap-6">
                    <div class="relative overflow-hidden rounded-[6px]">
                        <x-img src="{{ str_replace(' ', '%20', asset('images/image 7.jpg')) }}" alt="{{ $order['room'] }}"
                               sizes="(min-width:1024px) 50vw, 100vw" loading="lazy" decoding="async"
                               class="h-[320px] w-full object-cover sm:h-[480px] lg:h-[560px]" />
                        <div class="absolute inset-0 bg-gradient-to-b from-black/75 via-transparent to-black/30"></div>
                    </div>

                    <div class="flex flex-col gap-2 rounded-[6px] bg-[#373d35]/[0.34] p-6 sm:p-8 lg:p-10">
                        <h2 class="mb-4 text-3xl font-semibold tracking-tight text-white lg:text-h2">Order Details</h2>
                        <div class="flex flex-col text-body-sm font-semibold text-[#f5f5f5] lg:text-body-lg">
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M21 10.78V8a2 2 0 0 0-2-2h-5v5h-4V6H5a2 2 0 0 0-2 2v2.78A2 2 0 0 0 2 12.5V19h2v-2h16v2h2v-6.5a2 2 0 0 0-1-1.72z"/></svg>
                                    Room/Apartment
                                </span>
                                <span>{{ $order['room'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M16 4a3 3 0 0 1 0 6M21 20c0-2.5-1.5-4.6-3.6-5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Guest
                                </span>
                                <span>{{ $order['guests'] }} Guest</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zM5 9h14v10H5V9z"/></svg>
                                    Check-in / Check-out
                                </span>
                                <span>{{ $order['date_range'] }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                <span class="flex items-center gap-2">
                                    <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0-6 6c0 4 6 12 6 12s6-8 6-12a6 6 0 0 0-6-6z"/><circle cx="12" cy="9" r="2.5"/></svg>
                                    Stay Duration
                                </span>
                                <span>{{ $order['nights'] }} {{ \Illuminate\Support\Str::plural('Night', $order['nights']) }}</span>
                            </div>
                            @if ($order['pickup_vehicle'])
                                <div class="flex items-center justify-between gap-4 border-t border-white/10 py-4">
                                    <span class="flex items-center gap-2">
                                        <svg class="icon-md shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
                                        Vehicle Pickup
                                    </span>
                                    <span>{{ $order['pickup_vehicle'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ============ RIGHT: success + customer + actions ============ --}}
                <div class="flex max-w-[441px] flex-col gap-[57px]">
                    <div class="flex flex-col gap-[35px]">
                        <div class="flex flex-col gap-[29px]">
                            {{-- Check circle --}}
                            <img loading="lazy" src="{{ asset('images/checkcircle.png') }}" alt="Success"
                                 class="size-[120px] shrink-0 object-contain lg:size-[167px]">
                            <div class="flex flex-col gap-1">
                                <h1 class="text-h3 font-bold tracking-tight text-[#f38c00] lg:text-h2">Reservation Successful!</h1>
                                <p class="text-body text-white">Your reservation is successful.</p>
                            </div>
                        </div>

                        {{-- Customer details --}}
                        <div class="flex flex-col gap-[30px] pl-2 text-body-lg font-medium text-white lg:text-body-lg">
                            <p>Customer Details</p>
                            <div class="flex flex-col gap-3 text-body lg:text-body-lg">
                                <p class="flex flex-wrap gap-2"><span>Name:</span><span>{{ $order['customer_name'] ?: '—' }}</span></p>
                                <p class="flex flex-wrap gap-2"><span>Contact number:</span><span>{{ $order['customer_phone'] ?: '—' }}</span></p>
                                <p class="flex flex-wrap gap-2"><span>Email Address:</span><span class="break-all">{{ $order['customer_email'] ?: '—' }}</span></p>
                            </div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col gap-[29px]">
                        <a href="{{ route('checkout.receipt') }}" target="_blank" rel="noopener"
                           class="flex h-[75px] w-full items-center justify-center gap-2.5 rounded-[6px] bg-[#ba6d04] text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03]">
                            Download Receipt
                            <svg class="icon-lg shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        </a>
                        <a href="{{ route('home') }}" wire:navigate
                           class="flex h-[75px] w-full items-center justify-center rounded-[6px] border border-white text-body-lg font-medium tracking-tight text-white transition hover:bg-white/10 lg:text-title">
                            Back to Homepage
                        </a>
                    </div>
                </div>
            </div>
        </x-layouts.container>
    </section>
</x-layouts.web>
