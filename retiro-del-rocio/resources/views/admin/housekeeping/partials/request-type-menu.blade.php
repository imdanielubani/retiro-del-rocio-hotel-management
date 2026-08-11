{{-- Row actions for a housekeeping request type ($t). Mirrors the apartment-bookings action menu. --}}
<div x-data="{ open: false }" class="relative flex justify-end">
    <button type="button" @click="open = !open"
            :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
            class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         class="absolute right-0 top-9 z-50 w-[232px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
        <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
            <p class="text-[11px] text-[#9ca3af]">{{ $t->key }}</p>
            <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $t->label }}</p>
        </div>

        <button type="button" @click="open = false" wire:click="openEdit({{ $t->id }})"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            Edit
        </button>

        @if ($t->key !== \App\Models\HousekeepingRequest::CHECKOUT_INSPECTION)
            <button type="button" @click="open = false" wire:click="toggleGuestVisible({{ $t->id }})"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                @if ($t->guest_visible)
                    <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-6.5 0-10-7-10-7a19.9 19.9 0 0 1 4.34-5.6M9.9 4.24A10.4 10.4 0 0 1 12 4c6.5 0 10 7 10 7a19.9 19.9 0 0 1-2.66 3.79M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M2 2l20 20"/></svg>
                    Hide From Guest Tablet
                @else
                    <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    Show On Guest Tablet
                @endif
            </button>
        @endif

        <button type="button" @click="open = false" wire:click="toggleActive({{ $t->id }})"
                class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            @if ($t->is_active)
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                Deactivate
            @else
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Activate
            @endif
        </button>

        @if ($t->key !== \App\Models\HousekeepingRequest::CHECKOUT_INSPECTION)
            <button type="button" @click="open = false" wire:click="delete({{ $t->id }})"
                    wire:confirm="Delete {{ $t->label }}? This cannot be undone."
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                Delete
            </button>
        @endif
    </div>
</div>
