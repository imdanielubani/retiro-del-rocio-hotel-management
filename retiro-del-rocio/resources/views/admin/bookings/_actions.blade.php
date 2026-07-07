{{-- Status-aware action menu for a booking ($b). --}}
<div x-data="{ open: false }" class="relative flex justify-end">
    <button type="button" @click="open = !open"
            :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
            class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         class="absolute right-0 top-9 z-50 w-[232px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
        <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
            <p class="text-[11px] text-[#9ca3af]">{{ $b->bookingCode() }}</p>
            <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
        </div>

        <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate @click="open = false"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            View Details
        </a>
        <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate @click="open = false"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Edit Booking
        </a>

        @if ($b->status === 'pending')
            <button type="button" @click="open = false" wire:click="confirm({{ $b->id }})"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#16a34a] transition hover:bg-[#f0fdf4]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Confirm
            </button>
        @endif

        @if ($b->status === 'paid')
            <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate @click="open = false"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                Check In
            </a>
        @endif

        @if ($b->status === 'checked_in')
            <button type="button" @click="open = false" wire:click="checkOut({{ $b->id }})"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Check Out
            </button>
        @endif

        @if ($b->status !== 'cancelled')
            <button type="button" @click="open = false" wire:click="cancel({{ $b->id }})"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                Cancel Booking
            </button>
        @endif
    </div>
</div>
