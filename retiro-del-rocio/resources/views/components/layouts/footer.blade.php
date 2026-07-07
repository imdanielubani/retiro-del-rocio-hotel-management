@php
    // Editable in Admin → Website CMS → Footer Editor.
    $helpfulLinks = collect(cms_array('footer.links'))->map(fn ($l) => [
        'label' => $l['label'] ?? '',
        'href' => $l['url'] ?? '#',
    ])->all();
@endphp

<footer class="w-full bg-[#1e1e1e] text-white">
    <x-layouts.container class="pt-[38px] pb-8 lg:pb-10">
        {{-- Top content: even, responsive columns (no uneven flex-wrap on laptops) --}}
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8 xl:gap-12">
            {{-- Brand --}}
            <div class="flex max-w-[421px] flex-col gap-[11px]">
                <div class="flex flex-col gap-[6px]">
                    <img loading="lazy" src="{{ cms_image('footer.logo') }}" alt="Retiro Del Rocio"
                         class="h-auto w-[170px] object-contain">
                </div>
                <p class="text-body font-medium leading-snug tracking-tight">
                    {{ cms('footer.brand_text') }}
                </p>
            </div>

            {{-- Helpful Links --}}
            <div class="flex flex-col gap-[29px]">
                <p class="text-title font-medium tracking-tight">Helpful Links</p>
                <div class="flex flex-col gap-[26px]">
                    @foreach ($helpfulLinks as $link)
                        <a href="{{ $link['href'] }}"
                           class="text-body-lg tracking-tight transition hover:text-[#ba6d04]">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Get in touch + Follow Us --}}
            <div class="flex flex-col gap-[22px]">
                <div class="flex flex-col gap-[14px]">
                    <p class="text-title font-medium tracking-tight">Get in touch</p>
                    <a href="mailto:{{ cms('footer.email') }}"
                       class="text-body-lg tracking-tight transition hover:text-[#ba6d04]">
                        {{ cms('footer.email') }}
                    </a>
                </div>
                <div class="flex flex-col gap-[12px]">
                    <p class="text-title font-medium tracking-tight">Follow Us</p>
                    <div class="flex items-center gap-[35px]">
                        {{-- Facebook --}}
                        <a href="{{ cms('contact.facebook') }}" aria-label="Facebook" class="text-white transition hover:text-[#ba6d04]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg>
                        </a>
                        {{-- X / Twitter --}}
                        <a href="{{ cms('contact.x') }}" aria-label="X" class="text-white transition hover:text-[#ba6d04]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.66l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25h6.83l4.713 6.231 5.447-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>
                        </a>
                        {{-- Instagram --}}
                        <a href="{{ cms('contact.instagram') }}" aria-label="Instagram" class="text-white transition hover:text-[#ba6d04]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>
                        </a>
                        {{-- LinkedIn --}}
                        <a href="{{ cms('contact.linkedin') }}" aria-label="LinkedIn" class="text-white transition hover:text-[#ba6d04]">
                            <svg class="icon-lg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="flex max-w-[334px] flex-col gap-[19px] text-body-lg font-medium tracking-tight">
                <p>{{ cms('footer.address') }}</p>
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', cms('footer.phone')) }}" class="transition hover:text-[#ba6d04]">{{ cms('footer.phone') }}</a>
            </div>
        </div>

        {{-- Divider --}}
        <hr class="mt-12 border-white/15 lg:mt-[60px]">

        {{-- Copyright + policy links --}}
        @php $policyLinks = cms_array('footer.policy_links'); @endphp
        <div class="flex flex-col items-center gap-4 pt-[38px] sm:flex-row sm:justify-between">
            <div class="flex items-center gap-2">
                <svg class="icon-sm shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M14.83 9.17a4 4 0 1 0 0 5.66" stroke-linecap="round"/></svg>
                <p class="text-body-sm font-medium tracking-tight sm:text-body">{{ cms('footer.copyright') }}</p>
            </div>
            @if (! empty($policyLinks))
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
                    @foreach ($policyLinks as $pl)
                        <a href="{{ $pl['url'] ?? '#' }}" class="text-body-sm font-medium tracking-tight transition hover:text-[#ba6d04]">{{ $pl['label'] ?? '' }}</a>
                        @if (! $loop->last)
                            <span class="h-4 w-px bg-white/25"></span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </x-layouts.container>
</footer>
