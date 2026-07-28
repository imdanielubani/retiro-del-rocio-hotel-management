{{-- Guest bill detail — centered modal (consistent with the other modules). --}}
@if ($showDetail && $selected)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4" wire:key="bill-detail-{{ $selectedBookingId }}">
        <div class="absolute inset-0 bg-black/50" wire:click="closeDetail"></div>

        <div class="relative z-10 flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
             x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-[#e5e7eb] px-6 py-5">
                <div>
                    <h3 class="text-[18px] font-bold text-[#1e1e1e]">{{ $selected['guest_name'] }}</h3>
                    <p class="mt-1 text-[12px] text-[#6b7280]">
                        {{ $selected['room_name'] ?: '—' }}
                        @if ($selected['unit_label']) · {{ $selected['unit_label'] }} @endif
                        @if ($selected['check_in_label'] && $selected['check_out_label'])
                            · {{ $selected['check_in_label'] }} – {{ $selected['check_out_label'] }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeDetail" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                {{-- Charge categories --}}
                <div class="flex flex-col gap-3">
                    @foreach ($selected['categories'] as $category)
                        <div class="rounded-xl border border-[#e5e7eb] px-4 py-3.5">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[13px] font-semibold text-[#1e1e1e]">{{ $category['label'] }}</p>
                                    <p class="text-[11px] text-[#9ca3af]">{{ $category['item_count'] }} {{ $category['item_count'] === 1 ? 'item' : 'items' }}</p>
                                </div>
                                @if ($category['has_charges'])
                                    <p class="text-[14px] font-bold text-[#1e1e1e]">{{ $category['amount_label'] }}</p>
                                @else
                                    <p class="text-[12px] text-[#9ca3af]">No charges</p>
                                @endif
                            </div>
                            @if (! empty($category['items']))
                                <div class="mt-3 flex flex-col gap-2 border-t border-[#f1f1ee] pt-3">
                                    @foreach ($category['items'] as $item)
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-[12.5px] font-medium text-[#374151]">{{ $item['label'] }}</p>
                                                @if ($item['sub'])
                                                    <p class="truncate text-[11px] text-[#9ca3af]">{{ $item['sub'] }}</p>
                                                @endif
                                            </div>
                                            <p class="shrink-0 text-[12.5px] font-medium text-[#374151]">{{ $item['amount_label'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Bill summary --}}
                <div class="mt-5 rounded-xl border border-[#f38c00]/30 bg-[#fff7ed] px-4 py-4">
                    <p class="text-[12px] font-semibold uppercase tracking-[0.5px] text-[#c2620a]">Outstanding Balance</p>
                    @forelse ($selected['summary_lines'] as $line)
                        <div class="mt-2 flex items-center justify-between text-[12.5px] text-[#374151]">
                            <span>{{ $line['label'] }}</span>
                            <span class="font-medium">{{ $line['amount_label'] }}</span>
                        </div>
                    @empty
                        <p class="mt-2 text-[12.5px] text-[#6b7280]">The room rate is settled at booking — nothing has been charged to the room during this stay.</p>
                    @endforelse
                    <div class="mt-3 flex items-center justify-between border-t border-[#f38c00]/20 pt-3">
                        <span class="text-[13px] font-bold text-[#1e1e1e]">Total Due</span>
                        <span class="text-[18px] font-bold text-[#f38c00]">{{ $selected['total_due_label'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
