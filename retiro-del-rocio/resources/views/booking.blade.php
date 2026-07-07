<x-layouts.web title="Booking Policy — Retiro Del Rocio"
    description="How reservations, payments, check-in/out, cancellations, and refunds work at Retiro Del Rocio.">

    @php $sections = cms_array('booking.sections'); @endphp

    {{-- ============================ HEADER ============================ --}}
    <section class="w-full pt-24 lg:pt-35">
        <x-layouts.container class="flex flex-col items-center gap-3 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-display">{{ cms('booking.title') }}</h1>
            <p class="text-body font-medium text-[#f38c00]">{{ cms('booking.updated') }}</p>
            <p class="max-w-[820px] text-body leading-relaxed tracking-tight text-white/75 lg:text-body-lg">{{ cms('booking.intro') }}</p>
        </x-layouts.container>
    </section>

    {{-- ============================ SECTIONS ============================ --}}
    <section class="w-full py-10 pb-20 lg:py-14 lg:pb-28">
        <x-layouts.container>
            <div class="flex flex-col gap-9 lg:gap-12">
                @forelse ($sections as $i => $s)
                    <div class="flex flex-col gap-3">
                        <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl lg:text-h2">
                            {{ $i + 1 }}. {{ $s['title'] ?? '' }}
                        </h2>
                        <div class="whitespace-pre-line text-body leading-relaxed tracking-tight text-white/75 lg:text-body-lg">{{ $s['text'] ?? '' }}</div>
                    </div>
                @empty
                    <p class="text-white/70">Our booking policy will be available here shortly.</p>
                @endforelse
            </div>
        </x-layouts.container>
    </section>

</x-layouts.web>
