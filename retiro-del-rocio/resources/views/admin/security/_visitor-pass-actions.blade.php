{{-- Status-aware action menu for a visitor pass ($p). Mirrors the booking menu. --}}
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

        <button type="button" @click="open = false" wire:click="view({{ $p->id }})"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            View Details
        </button>

        @if ($p->isInside())
            <button type="button" @click="open = false" wire:click="markExited({{ $p->id }})"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Mark Exited
            </button>
        @endif

        @if ($p->isOpen())
            @if (in_array($p->ttlock_status, ['offline', 'failed']))
                <button type="button" @click="open = false" wire:click="retryTtlock({{ $p->id }})"
                        class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                    Retry TTLock
                </button>
            @endif
            @if ($p->visitor_email)
                <button type="button" @click="open = false" wire:click="resendEmail({{ $p->id }})"
                        class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="m22 6-10 7L2 6"/></svg>
                    Re-send Email
                </button>
            @endif
            <button type="button" @click="open = false" wire:click="deny({{ $p->id }})" wire:confirm="Deny this visitor entry? Their code will stop working."
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#b45309] transition hover:bg-[#fffbeb]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                Deny Entry
            </button>
            <button type="button" @click="open = false" wire:click="revoke({{ $p->id }})" wire:confirm="Revoke this visitor pass? Their code will stop working."
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                Revoke Pass
            </button>
        @endif
    </div>
</div>
