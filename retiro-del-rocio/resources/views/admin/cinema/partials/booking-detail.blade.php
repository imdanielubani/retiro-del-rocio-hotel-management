{{-- Cinema booking detail — centered modal. --}}
@if ($showDetail && $selected)
    @php [$sC,$sB]=$selected->statusColors(); [$pC,$pB]=$selected->paymentColors(); @endphp
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4" wire:key="cb-detail-{{ $selected->id }}">
        <div class="absolute inset-0 bg-black/50" wire:click="closeDetail"></div>
        <div class="relative z-10 flex max-h-[90vh] w-full max-w-[480px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-[#e5e7eb] px-6 py-5">
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-[0.5px] text-[#f38c00]">{{ $selected->code }}</p>
                    <h3 class="mt-1 text-[18px] font-bold text-[#1e1e1e]">{{ $selected->customer_name ?: 'Guest' }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $selected->statusLabel() }}</span>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $pB }}; color: {{ $pC }};">{{ $selected->paymentLabel() }}</span>
                    </div>
                </div>
                <button type="button" wire:click="closeDetail" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div class="flex flex-col gap-2.5">
                    @if ($selected->customer_email)<div class="flex items-center gap-2.5 text-[13px] text-[#374151]"><svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $selected->customer_email }}</div>@endif
                    @if ($selected->customer_phone)<div class="flex items-center gap-2.5 text-[13px] text-[#374151]"><svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>{{ $selected->customer_phone }}</div>@endif
                </div>
                <div class="mt-4 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                    <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Movie</p>
                    <p class="mt-0.5 text-[14px] font-bold text-[#1e1e1e]">{{ $selected->movie_title }}</p>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Date</p><p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ optional($selected->show_date)->format('M j, Y') ?: '—' }}</p></div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Time</p><p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->show_time ?: '—' }}</p></div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Private Room</p><p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->roomLabel() }}</p></div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Guests</p><p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->guestsLabel() }}</p></div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 col-span-2"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Snacks</p><p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->snacksLabel() }}</p></div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 col-span-2"><p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Total paid</p><p class="mt-0.5 text-[16px] font-bold text-[#1e1e1e]">{{ $selected->amountLabel() }}</p></div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-t border-[#e5e7eb] px-6 py-4">
                <a href="{{ route('admin.cinema.receipt', $selected->id) }}" target="_blank" class="flex-1 rounded-lg border border-[#e5e7eb] px-4 py-2.5 text-center text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">Receipt</a>
                @if ($selected->status === 'confirmed')
                    <button type="button" wire:click="setStatus({{ $selected->id }}, 'used')" class="flex-1 rounded-lg bg-[#dcfce7] px-4 py-2.5 text-[13px] font-bold text-[#16a34a] transition hover:bg-[#bbf7d0]">Mark Used</button>
                @endif
                @if ($selected->status !== 'cancelled')
                    <button type="button" wire:click="cancelBooking({{ $selected->id }})" class="flex-1 rounded-lg bg-[#fee2e2] px-4 py-2.5 text-[13px] font-bold text-[#dc2626] transition hover:bg-[#fecaca]">Cancel</button>
                @endif
            </div>
        </div>
    </div>
@endif
