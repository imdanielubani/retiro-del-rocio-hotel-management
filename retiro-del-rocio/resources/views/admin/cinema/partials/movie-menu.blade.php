{{-- Movie card action menu (fixed positioning). $m = Movie. --}}
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative inline-block text-left">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) { const r = $refs.trigger.getBoundingClientRect(); const w=220, h=300, g=6; let left=Math.min(Math.max(8,r.right-w),window.innerWidth-w-8); let top=(r.bottom+h+g>window.innerHeight)?(r.top-h-g):(r.bottom+g); pos={top:Math.max(8,top)+'px',left:left+'px'}; }"
            :class="open ? 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]'"
            class="flex size-[30px] shrink-0 items-center justify-center rounded-lg border transition">
        <svg class="size-[16px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl" style="width: 220px;" :style="`top:${pos.top}; left:${pos.left}`">
        <div class="px-4 pb-2.5 pt-3">
            <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $m->title }}</p>
            <p class="text-[11px] text-[#9ca3af]">{{ $m->classificationLabel() }}</p>
        </div>
        <div class="h-px bg-[#f1f1ee]"></div>

        <button type="button" @click="open = false" wire:click="edit({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#1e1e1e] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
            Edit Movie
        </button>

        <a href="{{ route('cinema.movie', $m) }}" target="_blank" @click="open = false" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            View on Website
        </a>

        <button type="button" @click="open = false" wire:click="toggleFeatured({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#f38c00]" viewBox="0 0 24 24" fill="{{ $m->is_featured ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 15.1 8.3 22 9.3l-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            {{ $m->is_featured ? 'Remove from hero' : 'Feature in hero' }}
        </button>

        <button type="button" @click="open = false" wire:click="toggleActive({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ $m->is_active ? 'Hide from website' : 'Show on website' }}
        </button>

        <div class="h-px bg-[#f1f1ee]"></div>
        <button type="button" @click="open = false" wire:click="delete({{ $m->id }})" wire:confirm="Delete {{ $m->title }}? This cannot be undone." class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
            <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
            Delete Movie
        </button>
    </div>
</div>
