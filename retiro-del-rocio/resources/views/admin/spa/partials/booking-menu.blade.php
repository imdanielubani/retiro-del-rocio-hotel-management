{{-- Row action menu (Figma BookingMenu). Fixed positioning anchored via
     getBoundingClientRect so it's never clipped by the table overflow, and
     clamped within the viewport. $b = the SpaBooking row. --}}
@php
    $canComplete = ! in_array($b->status, ['cancelled', 'completed'], true);
    $canCancel = ! in_array($b->status, ['cancelled', 'completed'], true);
@endphp
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative inline-block text-left">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) {
                const r = $refs.trigger.getBoundingClientRect();
                const menuW = 230, menuH = 230, gap = 6;
                let left = Math.min(Math.max(8, r.right - menuW), window.innerWidth - menuW - 8);
                let top = (r.bottom + menuH + gap > window.innerHeight) ? (r.top - menuH - gap) : (r.bottom + gap);
                top = Math.max(8, top);
                pos = { top: top + 'px', left: left + 'px' };
            }"
            :class="open ? 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]'"
            class="flex size-[30px] items-center justify-center rounded-lg border transition">
        <svg class="size-[16px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl"
         style="width: 230px;" :style="`top:${pos.top}; left:${pos.left}`">
        {{-- Header: booking code + guest --}}
        <div class="px-4 pb-2.5 pt-3 text-right">
            <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $b->sessionCode() }}</p>
            <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: 'Guest' }}</p>
        </div>
        <div class="h-px bg-[#f1f1ee]"></div>

        {{-- View details --}}
        <button type="button" @click="open = false" wire:click="viewDetails({{ $b->id }})"
                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#1e1e1e] transition hover:bg-[#f9fafb]">
            <svg class="size-[17px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            View Details
        </button>

        @if ($canComplete)
            <button type="button" @click="open = false" wire:click="markCompleted({{ $b->id }})"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#7c3aed] transition hover:bg-[#f5f3ff]">
                <svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                Mark Completed
            </button>
        @endif

        @if ($canCancel)
            <button type="button" @click="open = false" wire:click="cancelSession({{ $b->id }})"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Reject / Cancel
            </button>
        @endif
    </div>
</div>
