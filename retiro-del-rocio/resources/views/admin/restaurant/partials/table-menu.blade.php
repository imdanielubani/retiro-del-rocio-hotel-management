{{-- Table/Lounge card action menu. $t = RestaurantTable. --}}
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) { const r = $refs.trigger.getBoundingClientRect(); const w=180, h=150, g=6; let left=Math.min(Math.max(8,r.right-w),window.innerWidth-w-8); let top=(r.bottom+h+g>window.innerHeight)?(r.top-h-g):(r.bottom+g); pos={top:Math.max(8,top)+'px',left:left+'px'}; }"
            :class="open ? 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]'"
            class="flex size-[30px] items-center justify-center rounded-lg border transition">
        <svg class="size-[16px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl" style="width: 180px;" :style="`top:${pos.top}; left:${pos.left}`">
        <button type="button" @click="open = false" wire:click="edit({{ $t->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#1e1e1e] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            Edit
        </button>
        <button type="button" @click="open = false" wire:click="toggleActive({{ $t->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5L16 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            {{ $t->is_active ? 'Deactivate' : 'Activate' }}
        </button>
        <div class="h-px bg-[#f1f1ee]"></div>
        <button type="button" @click="open = false" wire:click="delete({{ $t->id }})" wire:confirm="Delete {{ $t->name }}?" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
            <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            Delete
        </button>
    </div>
</div>
