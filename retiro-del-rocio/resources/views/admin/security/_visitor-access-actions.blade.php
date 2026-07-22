{{-- Action menu for an access-log row ($p). The log is read-only apart from
     marking a still-inside visitor as having left. --}}
@if ($p->isInside())
    <div x-data="{ open: false }" class="relative flex justify-end">
        <button type="button" @click="open = !open"
                :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
        </button>

        <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
             class="absolute right-0 top-9 z-50 w-[232px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
            <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
                <p class="text-[11px] text-[#9ca3af]">{{ $p->caseNumber() }}</p>
                <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $p->visitor_name }}</p>
            </div>
            <button type="button" @click="open = false" wire:click="markExited({{ $p->id }})"
                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Mark Exited
            </button>
        </div>
    </div>
@else
    <span class="text-[#d1d5db]">—</span>
@endif
