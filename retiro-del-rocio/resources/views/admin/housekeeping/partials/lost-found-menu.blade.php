{{-- Row actions for a lost & found item ($item). Mirrors the apartment-bookings action menu. --}}
<div x-data="{ open: false }" class="relative flex justify-end">
    <button type="button" @click="open = !open"
            :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
            class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         class="absolute right-0 top-9 z-50 w-[232px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
        <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
            <p class="text-[11px] text-[#9ca3af]">{{ $item->roomUnit?->number ? 'Room '.$item->roomUnit->number : 'No room' }}</p>
            <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $item->item_description }}</p>
        </div>

        @if ($item->isUnclaimed())
            <button type="button" @click="open = false" wire:click="openReturn({{ $item->id }})"
                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] font-medium text-[#16a34a] transition hover:bg-[#f0fdf4]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Mark Returned
            </button>
            <button type="button" @click="open = false" wire:click="markDisposed({{ $item->id }})"
                    wire:confirm="Mark {{ $item->item_description }} as disposed? This cannot be undone."
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                Mark Disposed
            </button>
        @else
            <div class="px-4 py-3 text-[12px] text-[#9ca3af]">
                {{ $item->status === 'returned' ? 'Already returned' : 'Already disposed' }}@if ($item->returned_at) · {{ $item->returned_at->diffForHumans() }}@endif
            </div>
        @endif
    </div>
</div>
