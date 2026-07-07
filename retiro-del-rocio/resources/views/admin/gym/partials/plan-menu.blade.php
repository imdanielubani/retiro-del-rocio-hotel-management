{{-- Plan 3-dot menu (fixed positioning so it's never clipped). $p = GymPlan. --}}
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) { const r = $refs.trigger.getBoundingClientRect(); const w=180; let left=Math.min(Math.max(8,r.right-w),window.innerWidth-w-8); let top=(r.bottom+200>window.innerHeight)?(r.top-200):(r.bottom+6); pos={top:Math.max(8,top)+'px',left:left+'px'}; }"
            class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]">
        <svg class="size-[18px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] w-[180px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl" :style="`top:${pos.top}; left:${pos.left}`">
        <button type="button" @click="open = false" wire:click="edit({{ $p->id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[15px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Edit
        </button>
        <button type="button" @click="open = false" wire:click="toggleActive({{ $p->id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[15px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12" stroke-linecap="round"/></svg>
            {{ $p->is_active ? 'Deactivate' : 'Activate' }}
        </button>
        <div class="my-1 h-px bg-[#f1f1ee]"></div>
        <button type="button" @click="open = false" wire:click="delete({{ $p->id }})" wire:confirm="Delete this plan?" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#dc2626] transition hover:bg-[#fef2f2]">
            <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
            Delete
        </button>
    </div>
</div>
