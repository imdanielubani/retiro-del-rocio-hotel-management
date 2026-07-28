{{-- Guest detail — centered modal (consistent with the other modules). --}}
@if ($showDetail && $selected)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4" wire:key="guest-detail-{{ $selectedId }}">
        <div class="absolute inset-0 bg-black/50" wire:click="closeDetail"></div>

        <div class="relative z-10 flex max-h-[90vh] w-full max-w-[520px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
             x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-[#e5e7eb] px-6 py-5">
                <div class="flex items-center gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#f38c00]/15 text-[16px] font-bold text-[#c2620a]">{{ $selected['initials'] }}</span>
                    <div>
                        <h3 class="text-[18px] font-bold text-[#1e1e1e]">{{ $selected['name'] }}</h3>
                        <div class="mt-1.5 flex items-center gap-2">
                            @if ($selected['in_house'])
                                <span class="inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-0.5 text-[11px] font-semibold text-[#16a34a]">In-House</span>
                            @elseif ($selected['total_stays'] > 1)
                                <span class="inline-flex items-center rounded-full bg-[#f3e8ff] px-2.5 py-0.5 text-[11px] font-semibold text-[#7c3aed]">Returning</span>
                            @endif
                            <span class="text-[12px] text-[#9ca3af]">Guest since {{ $selected['first_seen_label'] }}</span>
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="closeDetail" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                {{-- Contact --}}
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-center gap-2.5 text-[13px] text-[#374151]">
                        <svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        {{ $selected['email'] ?: '—' }}
                    </div>
                    <div class="flex items-center gap-2.5 text-[13px] text-[#374151]">
                        <svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        {{ $selected['phone'] ?: '—' }}
                    </div>
                </div>

                {{-- Lifetime stats --}}
                <div class="mt-5 grid grid-cols-2 gap-3">
                    @foreach ([
                        ['Total Stays', $selected['total_stays']],
                        ['Total Nights', $selected['total_nights']],
                        ['Total Spent', $selected['total_spend_label']],
                        ['Favourite Room', $selected['favourite_room']],
                    ] as [$label, $value])
                        <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">{{ $label }}</p>
                            <p class="mt-0.5 truncate text-[14px] font-semibold text-[#1e1e1e]">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Stay history --}}
                <p class="mt-6 text-[12px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Stay History</p>
                <div class="mt-2 flex flex-col gap-2">
                    @foreach ($selected['history'] as $b)
                        <div wire:key="ghist-{{ $b->id }}" class="flex items-center justify-between gap-3 rounded-xl border border-[#e5e7eb] px-4 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-medium text-[#1e1e1e]">{{ $b->room_name ?: '—' }}@if ($b->roomUnit) · Room {{ $b->roomUnit->number }}@endif</p>
                                <p class="text-[11px] text-[#9ca3af]">
                                    {{ optional($b->check_in)->format('M j') }} – {{ optional($b->check_out)->format('M j, Y') }} · {{ $b->nights }} {{ Str::plural('night', $b->nights) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="text-[13px] font-semibold text-[#1e1e1e]">{{ $b->totalWithVatLabel() }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
