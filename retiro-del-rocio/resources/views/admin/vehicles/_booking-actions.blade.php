{{-- Status-aware row action menu for a transport booking ($b). Matches the gym
     module: bordered ⋮ trigger + fixed-positioned menu (never clipped by the
     table's overflow container). --}}
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative inline-block text-left">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) { const r = $refs.trigger.getBoundingClientRect(); const w=220, h=220, g=6; let left=Math.min(Math.max(8,r.right-w),window.innerWidth-w-8); let top=(r.bottom+h+g>window.innerHeight)?(r.top-h-g):(r.bottom+g); pos={top:Math.max(8,top)+'px',left:left+'px'}; }"
            :class="open ? 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]'"
            class="flex size-[30px] items-center justify-center rounded-lg border transition">
        <svg class="size-[16px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl" style="width: 220px;" :style="`top:${pos.top}; left:${pos.left}`">
        <div class="px-4 pb-2.5 pt-3 text-right">
            <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $b->transportCode() }}</p>
            <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: 'Guest' }}</p>
        </div>
        <div class="h-px bg-[#f1f1ee]"></div>

        <button type="button" @click="open = false" wire:click="view({{ $b->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#1e1e1e] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            View Details
        </button>

        @if ($b->status === 'pending')
            <button type="button" @click="open = false" wire:click="confirm({{ $b->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#16a34a] transition hover:bg-[#f0fdf4]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                Confirm Booking
            </button>
            <div class="h-px bg-[#f1f1ee]"></div>
            <button type="button" @click="open = false" wire:click="reject({{ $b->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                Reject Booking
            </button>
        @endif
    </div>
</div>
