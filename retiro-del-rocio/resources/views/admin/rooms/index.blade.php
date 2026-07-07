<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Category summary chips ===== --}}
    @if ($categoryCounts->isNotEmpty())
        <div class="no-scrollbar flex flex-nowrap items-center gap-2.5 overflow-x-auto">
            @foreach ($categoryCounts as $type => $count)
                @php $cc = $categoryColors[$type] ?? '#6b7280'; $sub = $categorySubtitles[$type] ?? null; @endphp
                <button type="button" wire:click="setType(@js($type))"
                        @class([
                            'flex shrink-0 items-center gap-2 rounded-xl border bg-white px-3.5 py-2 transition hover:bg-[#f9fafb]',
                            'border-[#e5e7eb]' => $typeFilter !== $type,
                            'ring-2 ring-offset-1 border-transparent' => $typeFilter === $type,
                        ])
                        style="{{ $typeFilter === $type ? '--tw-ring-color: '.$cc.';' : '' }}">
                    <span class="size-3 shrink-0 rounded-[3px]" style="background: {{ $cc }}"></span>
                    <span class="whitespace-nowrap text-[13px] font-bold text-[#1e1e1e]">{{ $type }}</span>
                    @if ($sub)
                        <span class="whitespace-nowrap text-[12px] text-[#9ca3af]">— {{ $sub }}</span>
                    @endif
                    <span class="flex min-w-[20px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold" style="background: {{ $cc }}1a; color: {{ $cc }}">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ===== Toolbar: search + type chips + count + add ===== --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        {{-- Search --}}
        <div class="relative w-full lg:max-w-[340px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search rooms by name, type or floor…"
                   class="h-11 w-full rounded-full border border-[#e5e7eb] bg-white pl-10 pr-4 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>

        {{-- Type chips (single straight line, scrolls if needed) --}}
        <div class="no-scrollbar flex flex-nowrap items-center gap-2 overflow-x-auto">
            <button type="button" wire:click="$set('typeFilter', '')"
                    @class([
                        'shrink-0 rounded-full border px-4 py-2 text-[13px] font-medium transition',
                        'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $typeFilter === '',
                        'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $typeFilter !== '',
                    ])>All</button>
            @foreach ($types as $type)
                <button type="button" wire:click="setType(@js($type))"
                        @class([
                            'shrink-0 whitespace-nowrap rounded-full border px-4 py-2 text-[13px] font-medium transition',
                            'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $typeFilter === $type,
                            'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $typeFilter !== $type,
                        ])>{{ $type }}</button>
            @endforeach
        </div>

        {{-- Count + Add --}}
        <div class="flex items-center justify-between gap-3 lg:ml-auto">
            <p class="shrink-0 text-[13px] text-[#6b7280]">{{ $rooms->count() }} {{ Str::plural('room', $rooms->count()) }} found</p>
            <a href="{{ route('admin.rooms.create') }}" wire:navigate
               class="flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#f38c00] px-5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Room
            </a>
        </div>
    </div>

    {{-- ===== Cards grid ===== --}}
    @if ($rooms->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l8-4 8 4v14M9 9h.01M9 13h.01M9 17h.01"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No rooms found</p>
            <p class="text-[13px] text-[#6b7280]">{{ $search || $typeFilter ? 'Try a different search or filter.' : 'Add your first room to get started.' }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($rooms as $room)
                @php
                    $cc = $categoryColors[$room->type] ?? '#6b7280';
                    $hasUnits = ($room->units_total ?? 0) > 0;
                    $unitsFree = ($room->units_total ?? 0) - ($room->units_occupied ?? 0);
                    $occupied = $hasUnits ? ($unitsFree <= 0) : $occupiedIds->contains($room->id);
                @endphp
                            <div wire:key="room-{{ $room->id }}" x-data="{ confirming: false }"
                                 class="flex flex-col overflow-hidden rounded-2xl border border-[#e5e7eb] border-t-4 bg-white"
                                 style="border-top-color: {{ $cc }}">
                                {{-- Image + badges --}}
                                <div class="relative h-[170px] w-full bg-[#1e2318]">
                                    @if ($room->featuredUrl())
                                        <img src="{{ $room->featuredUrl() }}" alt="{{ $room->name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-[13px] text-white/40">Image uploads here</div>
                                    @endif

                                    {{-- Occupancy (left) --}}
                                    @if ($hasUnits)
                                        <span @class([
                                            'absolute left-3 top-3 rounded-md px-2.5 py-1 text-[11px] font-semibold',
                                            'bg-[#16a34a] text-white' => $unitsFree > 0,
                                            'bg-[#dc2626] text-white' => $unitsFree <= 0,
                                        ])>{{ $unitsFree > 0 ? $unitsFree.' / '.$room->units_total.' available' : 'Full' }}</span>
                                    @else
                                        <span @class([
                                            'absolute left-3 top-3 rounded-md px-2.5 py-1 text-[11px] font-semibold',
                                            'bg-[#16a34a] text-white' => $occupied,
                                            'bg-white/90 text-[#16a34a]' => ! $occupied,
                                        ])>{{ $occupied ? 'Occupied' : 'Available' }}</span>
                                    @endif

                                    {{-- Publish status (right) --}}
                                    <span @class([
                                        'absolute right-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                                        'bg-[#dcfce7] text-[#16a34a]' => $room->is_published,
                                        'bg-[#f3f4f6] text-[#6b7280]' => ! $room->is_published,
                                    ])>{{ $room->is_published ? 'Published' : 'Draft' }}</span>
                                </div>

                                {{-- Body --}}
                                <div class="flex flex-1 flex-col gap-3 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-[11px] font-semibold uppercase tracking-wide" style="color: {{ $cc }}">{{ $room->name }}</p>
                                            <p class="truncate text-[16px] font-bold text-[#1e1e1e]">{{ $room->type }}</p>
                                        </div>
                                        <p class="shrink-0 text-right">
                                            <span class="text-[16px] font-bold text-[#f38c00]">{{ $room->priceLabel() }}</span>
                                            <span class="block text-[11px] text-[#9ca3af]">/night</span>
                                        </p>
                                    </div>

                                    {{-- Spec chips (single line, never breaks) --}}
                                    <div class="no-scrollbar flex flex-nowrap gap-1.5 overflow-x-auto">
                                        @php
                                            $specs = [
                                                ['m3 8V3h5M21 8V3h-5M3 16v5h5M21 16v5h-5', $room->sqft.' sq ft'],
                                                ['M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6M3 14h18M5 18v2M19 18v2', $room->beds.' '.Str::plural('bed', $room->beds)],
                                                ['M9 8a3 3 0 1 0 0-.01M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6', $room->guests.' guests'],
                                                ['M4 12V6a2 2 0 0 1 4 0M3 12h18v2a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-2z', $room->bathrooms.' '.Str::plural('bath', $room->bathrooms)],
                                            ];
                                        @endphp
                                        @foreach ($specs as [$icon, $label])
                                            <span class="flex shrink-0 items-center gap-1 whitespace-nowrap rounded-md border border-[#e5e7eb] bg-[#f9fafb] px-2 py-1 text-[12px] text-[#374151]">
                                                <svg class="size-3.5 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                                                {{ $label }}
                                            </span>
                                        @endforeach
                                    </div>

                                    {{-- Actions --}}
                                    <div class="mt-auto flex items-center gap-2 pt-1">
                                        <a href="{{ route('admin.rooms.edit', $room) }}" wire:navigate
                                           class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e7eb] py-2 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            Edit
                                        </a>
                                        <a href="{{ route('rooms.show', $room) }}" target="_blank" rel="noopener"
                                           class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#e5e7eb] py-2 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Preview
                                        </a>
                                        <button type="button" wire:click="togglePublish({{ $room->id }})"
                                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border py-2 text-[13px] font-medium transition {{ $room->is_published ? 'border-[#fecaca] bg-[#fef2f2] text-[#dc2626] hover:bg-[#fee2e2]' : 'border-[#bbf7d0] bg-[#f0fdf4] text-[#16a34a] hover:bg-[#dcfce7]' }}">
                                            @if ($room->is_published)
                                                Unpublish
                                            @else
                                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                                Publish
                                            @endif
                                        </button>

                                        {{-- Delete with inline confirm --}}
                                        <div class="relative shrink-0">
                                            <button type="button" @click="confirming = true"
                                                    class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#dc2626] transition hover:bg-[#fef2f2]" title="Delete">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                            </button>
                                            <div x-show="confirming" x-cloak @click.outside="confirming = false" x-transition
                                                 class="absolute bottom-11 right-0 z-20 w-[220px] rounded-xl border border-[#e5e7eb] bg-white p-3 shadow-lg">
                                                <p class="text-[13px] text-[#374151]">Delete <span class="font-semibold">{{ $room->name }}</span>? This can't be undone.</p>
                                                <div class="mt-3 flex justify-end gap-2">
                                                    <button type="button" @click="confirming = false" class="rounded-lg px-3 py-1.5 text-[13px] text-[#6b7280] hover:bg-[#f3f4f6]">Cancel</button>
                                                    <button type="button" @click="confirming = false" wire:click="delete({{ $room->id }})" class="rounded-lg bg-[#dc2626] px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-[#b91c1c]">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
            @endforeach
        </div>
    @endif
</div>
