@php
    // Editable in Admin → Website CMS → Navigation Menu.
    $curr = trim(request()->path(), '/'); // '' for home
    $links = collect(cms_array('nav.links'))->map(function ($l) use ($curr) {
        $href = $l['url'] ?? '#';
        $path = trim((string) parse_url($href, PHP_URL_PATH), '/');
        return [
            'label' => $l['label'] ?? '',
            'href' => $href,
            'active' => $href !== '#' && $href !== '' && $path === $curr,
        ];
    })->all();
    $navCtaLabel = cms('nav.cta_label');
    $navCtaUrl = cms('nav.cta_url') ?: '#';
@endphp

<nav x-data="{ open: false }" class="relative z-50 w-full bg-[#232d22]">
    <x-layouts.container class="flex h-[80px] items-center justify-between lg:h-[114px]">
        {{-- Logo --}}
        <a href="{{ url('/') }}" class="flex shrink-0 items-center" wire:navigate>
            <img loading="lazy" src="{{ cms_image('nav.logo') }}" alt="Retiro Del Rocio"
                 class="h-[56px] w-auto object-contain lg:h-[89px]">
        </a>

        {{-- Desktop nav --}}
        <div class="hidden items-center gap-[18px] xl:flex xl:gap-[25px]">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" @if ($link['href'] !== '#') wire:navigate @endif
                   class="whitespace-nowrap text-body transition hover:text-[#f38c00] {{ $link['active'] ? 'font-black text-[#f38c00]' : 'font-medium text-white' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ $navCtaUrl }}" @if ($navCtaUrl !== '#') wire:navigate @endif
               class="flex h-[50px] w-[156px] items-center justify-center rounded-[13px] border border-[#c8c8c8] bg-[#ba6d04] text-body font-medium text-white transition hover:bg-[#a35f03]">
                {{ $navCtaLabel }}
            </a>
        </div>

        {{-- Mobile hamburger --}}
        <button type="button" @click="open = !open"
                class="inline-flex icon-xl items-center justify-center rounded-lg text-white xl:hidden"
                :aria-expanded="open" aria-label="Toggle navigation">
            <svg x-show="!open" class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg x-show="open" x-cloak class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
            </svg>
        </button>
    </x-layouts.container>

    {{-- Mobile drawer --}}
    <div x-show="open" x-cloak x-collapse class="border-t border-white/10 bg-[#232d22] xl:hidden">
        <div class="flex flex-col gap-1 px-5 py-4 sm:px-8">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" @if ($link['href'] !== '#') wire:navigate @endif
                   class="rounded-lg px-3 py-3 text-body-sm transition hover:bg-white/5 {{ $link['active'] ? 'font-black text-[#f38c00]' : 'font-medium text-white' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ $navCtaUrl }}" @if ($navCtaUrl !== '#') wire:navigate @endif
               class="mt-2 flex h-[50px] items-center justify-center rounded-[13px] border border-[#c8c8c8] bg-[#ba6d04] text-body font-medium text-white transition hover:bg-[#a35f03]">
                {{ $navCtaLabel }}
            </a>
        </div>
    </div>
</nav>
