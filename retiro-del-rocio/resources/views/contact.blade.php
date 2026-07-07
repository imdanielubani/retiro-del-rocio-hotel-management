<x-layouts.web title="Get in Touch — Retiro Del Rocio"
    description="Get in touch with Retiro Del Rocio in Jos, Plateau State. Reach our team by phone, email, or the contact form for reservations, enquiries, and partnerships.">

    @php
        $faqs = cms_array('contact.faqs');
    @endphp

    {{-- ==================== GET IN TOUCH (form + details) ==================== --}}
    <section class="w-full py-16 lg:py-24">
        <x-layouts.container class="grid grid-cols-1 gap-12 lg:grid-cols-[760px_1fr] lg:gap-14 xl:gap-20">

            {{-- Form card --}}
            <div class="min-w-0 rounded-2xl bg-[rgba(17,15,6,0.26)] p-6 sm:p-10 lg:px-[56px] lg:py-[80px]">
                <form method="POST" action="{{ route('contact.submit') }}" class="flex flex-col gap-[44px]">
                    @csrf

                    <div class="flex flex-col">
                        <h1 class="text-4xl font-bold tracking-tight text-[#f38c00] lg:text-h1">{{ cms('contact.form_title') }}</h1>
                        <p class="mt-1 text-xl font-medium tracking-tight text-white lg:text-h3">{{ cms('contact.form_subtitle') }}</p>
                    </div>

                    {{-- First / Last name --}}
                    <div class="flex flex-col gap-[22px] sm:flex-row">
                        <div class="flex flex-1 flex-col gap-[19px]">
                            <label for="first_name" class="text-body-lg font-medium tracking-tight text-white">First Name <span class="text-red-500">*</span></label>
                            <input id="first_name" name="first_name" type="text" required value="{{ old('first_name') }}"
                                   class="h-[72px] w-full rounded-[15px] border border-[#c8c8c8] bg-[#373d35] px-5 text-white outline-none transition focus:border-[#f38c00]">
                            @error('first_name') <span class="text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-1 flex-col gap-[19px]">
                            <label for="last_name" class="text-body-lg font-medium tracking-tight text-white">Last Name <span class="text-red-500">*</span></label>
                            <input id="last_name" name="last_name" type="text" required value="{{ old('last_name') }}"
                                   class="h-[72px] w-full rounded-[15px] border border-[#c8c8c8] bg-[#373d35] px-5 text-white outline-none transition focus:border-[#f38c00]">
                            @error('last_name') <span class="text-sm text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Email --}}
                    <div class="flex flex-col gap-[19px]">
                        <label for="email" class="text-body-lg font-medium tracking-tight text-white">Email <span class="text-red-500">*</span></label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}"
                               class="h-[72px] w-full rounded-[15px] border border-[#c8c8c8] bg-[#373d35] px-5 text-white outline-none transition focus:border-[#f38c00]">
                        @error('email') <span class="text-sm text-red-400">{{ $message }}</span> @enderror
                    </div>

                    {{-- Phone --}}
                    <div class="flex flex-col gap-[19px]">
                        <label for="phone" class="text-body-lg font-medium tracking-tight text-white">Phone Number</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                               class="h-[72px] w-full rounded-[15px] border border-[#c8c8c8] bg-[#373d35] px-5 text-white outline-none transition focus:border-[#f38c00]">
                    </div>

                    {{-- Message --}}
                    <div class="flex flex-col gap-[19px]">
                        <label for="message" class="text-body-lg font-medium tracking-tight text-white">How can we help?</label>
                        <textarea id="message" name="message" rows="6"
                                  class="min-h-[250px] w-full resize-y rounded-[15px] border border-[#c8c8c8] bg-[#373d35] p-5 text-white outline-none transition focus:border-[#f38c00]">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit"
                            class="mx-auto h-[68px] w-full max-w-[468px] rounded-[8px] bg-[#f38c00] text-body-lg font-medium text-white transition hover:bg-[#dd7f00]">
                        Submit
                    </button>
                </form>
            </div>

            {{-- Enquiries / contact details --}}
            <div class="flex min-w-0 flex-col gap-9">
                <div class="flex flex-col gap-[46px]">
                    <div class="flex flex-col gap-[21px]">
                        <div class="flex flex-col font-bold">
                            <p class="text-body-lg tracking-tight text-[#787878]">{{ cms('contact.enquiry_eyebrow') }}</p>
                            <p class="text-4xl tracking-tight text-[#f38c00] lg:text-[58px] lg:leading-none">{{ cms('contact.enquiry_title') }}</p>
                        </div>
                        <p class="text-body-lg font-medium leading-[29px] text-white">
                            {{ cms('contact.enquiry_text') }}
                        </p>
                    </div>

                    {{-- Location --}}
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 icon-lg shrink-0 text-[#f38c00]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                        <p class="text-body-lg font-medium tracking-tight text-white">
                            {{ cms('contact.address') }}
                        </p>
                    </div>
                </div>

                {{-- Chat with us --}}
                <div class="flex flex-col gap-4">
                    <p class="text-title font-semibold tracking-tight text-[#d3d3d3]">Chat with us</p>
                    <a href="mailto:{{ cms('contact.email') }}" class="flex items-center gap-2 transition hover:text-[#f38c00]">
                        <img loading="lazy" src="{{ asset('images/mdi_email-open.png') }}" alt="" class="icon-md shrink-0 object-contain">
                        <span class="break-all text-body-lg font-medium tracking-tight text-white">{{ cms('contact.email') }}</span>
                    </a>
                </div>

                {{-- Call us --}}
                <div class="flex flex-col gap-4">
                    <p class="text-title font-semibold text-[#d3d3d3]">Call us</p>
                    <div class="flex flex-col gap-2">
                        <p class="text-body-lg text-[#d3d3d3] tracking-tight">{{ cms('contact.phone_note') }}</p>
                        <p class="text-body-lg text-[#d3d3d3]">{{ cms('contact.hours') }}</p>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', cms('contact.phone')) }}" class="flex items-center gap-2 transition hover:text-[#f38c00]">
                            <svg class="icon-md shrink-0 text-[#f38c00]" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.46.57 3.6a1 1 0 0 1-.25 1l-2.22 2.2z"/></svg>
                            <span class="text-body-lg font-medium tracking-tight text-white">{{ cms('contact.phone') }}</span>
                        </a>
                    </div>
                </div>

                {{-- Social media --}}
                <div class="flex flex-col gap-[18px]">
                    <div class="flex flex-col gap-[11px] text-[#d3d3d3]">
                        <p class="text-title font-semibold">Social media</p>
                        <p class="text-body-lg tracking-tight">Follow us on any of our socials to stay up-to-date</p>
                    </div>
                    <div class="flex items-center gap-[35px]">
                        <a href="{{ cms('contact.facebook') }}" aria-label="Facebook" class="text-white transition hover:text-[#f38c00]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg>
                        </a>
                        <a href="{{ cms('contact.x') }}" aria-label="X" class="text-white transition hover:text-[#f38c00]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.66l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25h6.83l4.713 6.231 5.447-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
                        </a>
                        <a href="{{ cms('contact.instagram') }}" aria-label="Instagram" class="text-white transition hover:text-[#f38c00]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>
                        </a>
                        <a href="{{ cms('contact.linkedin') }}" aria-label="LinkedIn" class="text-white transition hover:text-[#f38c00]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </x-layouts.container>
    </section>

    {{-- ========================= FAQ ACCORDION ========================= --}}
    <section class="w-full py-16 lg:py-24">
        <x-layouts.container class="flex flex-col gap-[80px]">
            <div class="flex flex-col items-center gap-4 text-center">
                <h2 class="font-['Manrope'] text-3xl font-bold uppercase text-white lg:text-h2">Frequently Asked Questions</h2>
                <p class="text-body capitalize text-[#e0e0e4] lg:text-body-lg">find answers to their most common questions quickly and easily.</p>
            </div>

            <div class="flex flex-col gap-[18px]" x-data="{ open: null }">
                @foreach ($faqs as $i => $faq)
                    <div class="overflow-hidden rounded-[8px] border border-[#373d35] bg-[#1e2318]">
                        <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                            <span class="font-['Manrope'] text-body-sm tracking-wide text-white lg:text-body">{{ $faq['q'] }}</span>
                            <svg class="icon-md shrink-0 text-white transition-transform duration-200" :class="{ 'rotate-45': open === {{ $i }} }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse x-cloak>
                            <p class="px-6 pb-5 font-['Manrope'] text-body-sm leading-relaxed text-[#e0e0e4] lg:text-body-sm">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-layouts.container>
    </section>
</x-layouts.web>
