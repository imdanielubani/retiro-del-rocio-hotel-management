<x-layouts.web title="Rooms & Apartment — Retiro Del Rocio"
    description="Browse rooms and apartments at Retiro Del Rocio in Jos — Rocio Rooms, Alba Suites, Rocio Loft Residence, and Brisa Residence. Luxury suites with curated comfort and amenities.">

    @php
        $rooms = \App\Models\Room::published()->ordered()->get();
        $offers = $rooms->take(2);

        // Build the filter chips from the distinct room types in the catalogue.
        $types = $rooms->pluck('type')->filter()->unique()->values();
        $heading = 'Retiro Del Rocio Rooms';
    @endphp

    <div x-data="{
        category: @js($types->contains(request('category')) ? request('category') : 'all'),
        rooms: @js($rooms->map(fn ($r) => ['name' => $r->name, 'type' => (string) $r->type])->values()),
        matches(type) {
            return this.category === 'all' || type === this.category;
        },
        get anyVisible() {
            return this.rooms.some(r => this.matches(r.type));
        },
    }">
        {{-- ============================ HEADER / CATEGORIES ============================ --}}
        <section class="w-full pt-8 pb-10 sm:pt-10 sm:pb-12 lg:pt-12 lg:pb-16">
            <x-layouts.container class="flex flex-col gap-6 sm:gap-8 lg:flex-row lg:items-end lg:justify-between lg:gap-10">
                <h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:shrink-0 lg:whitespace-nowrap lg:text-[32px] xl:text-[40px] lg:leading-none">
                    {{ $heading }}
                </h1>

                {{-- Category chips: always a single straight line; scrolls horizontally if it overflows --}}
                <div class="no-scrollbar -mx-4 flex max-w-full flex-nowrap gap-2.5 overflow-x-auto px-4 sm:mx-0 sm:gap-3 sm:px-0 lg:min-w-0 lg:gap-[14px]">
                    <button type="button" @click="category = 'all'"
                            :class="category === 'all' ? 'bg-[#f38c00]' : 'bg-[#f6f6f6]/75 hover:bg-[#f6f6f6]'"
                            class="flex h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-[8px] border-[0.5px] border-black/20 px-4 text-body-sm font-bold tracking-tight text-black transition sm:h-[52px] sm:px-5 sm:text-body lg:h-[58px] lg:px-[26px] lg:text-body-lg">
                        All
                    </button>
                    @foreach ($types as $type)
                        <button type="button" @click="category = @js($type)"
                                :class="category === @js($type) ? 'bg-[#f38c00]' : 'bg-[#f6f6f6]/75 hover:bg-[#f6f6f6]'"
                                class="flex h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-[8px] border-[0.5px] border-black/20 px-4 text-body-sm font-bold tracking-tight text-black transition sm:h-[52px] sm:px-5 sm:text-body lg:h-[58px] lg:px-[26px] lg:text-body-lg">
                            {{ $type }}
                        </button>
                    @endforeach
                </div>
            </x-layouts.container>
        </section>

        {{-- ============================ ROOMS GRID ============================ --}}
        <section class="w-full">
            <x-layouts.container class="grid grid-cols-1 gap-3 sm:gap-[15px] md:grid-cols-2">
                @forelse ($rooms as $room)
                    <div wire:key="room-{{ $room->id }}" x-show="matches(@js((string) $room->type))">
                        <x-layouts.room-card :room="$room" />
                    </div>
                @empty
                    <p class="text-body-lg text-white/70">No rooms are published yet.</p>
                @endforelse

                {{-- No results for the active category --}}
                <p x-show="!anyVisible" x-cloak style="display:none"
                   class="py-10 text-center text-body-lg text-white/70 md:col-span-2">
                    No rooms in this category yet. Try another category.
                </p>
            </x-layouts.container>
        </section>
    </div>

    {{-- ===================== EXPLORE OUR EXCLUSIVE OFFERS ===================== --}}
    <section class="w-full py-12 sm:py-16 lg:py-24">
        <x-layouts.container class="flex flex-col gap-10 sm:gap-[60px] lg:gap-[90px]">
            <h2 class="text-center text-2xl tracking-tight text-white sm:text-3xl lg:text-h2">Explore our exclusive offers</h2>

            <div class="grid grid-cols-1 gap-3 sm:gap-[15px] md:grid-cols-2">
                @foreach ($offers as $room)
                    <x-layouts.room-card :room="$room" />
                @endforeach
            </div>
        </x-layouts.container>
    </section>
</x-layouts.web>
