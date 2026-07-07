{{-- Membership row action menu (fixed positioning). $m = GymMembership. --}}
@php
    $canRenew = $m->status !== 'cancelled';
    $canSuspend = $m->status === 'active';
    $canReactivate = $m->status === 'suspended';
    $canRecord = $m->payment_status !== 'paid' && $m->status !== 'cancelled';
    $canCancel = $m->status !== 'cancelled';
@endphp
<div x-data="{ open: false, pos: { top: '0px', left: '0px' } }" class="relative inline-block text-left">
    <button type="button" x-ref="trigger"
            @click="open = !open; if (open) { const r = $refs.trigger.getBoundingClientRect(); const w=220, h=360, g=6; let left=Math.min(Math.max(8,r.right-w),window.innerWidth-w-8); let top=(r.bottom+h+g>window.innerHeight)?(r.top-h-g):(r.bottom+g); pos={top:Math.max(8,top)+'px',left:left+'px'}; }"
            :class="open ? 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]'"
            class="flex size-[30px] items-center justify-center rounded-lg border transition">
        <svg class="size-[16px]" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity.duration.100ms
         class="fixed z-[100] overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white shadow-xl" style="width: 220px;" :style="`top:${pos.top}; left:${pos.left}`">
        <div class="px-4 pb-2.5 pt-3 text-right">
            <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $m->code }}</p>
            <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $m->customer_name ?: 'Member' }}</p>
        </div>
        <div class="h-px bg-[#f1f1ee]"></div>

        <button type="button" @click="open = false" wire:click="viewDetails({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#1e1e1e] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            View Details
        </button>

        @if ($canRenew)
            <button type="button" @click="open = false" wire:click="renewMembership({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#16a34a] transition hover:bg-[#f0fdf4]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5"/></svg>
                Renew Membership
            </button>
        @endif

        @if ($canSuspend)
            <button type="button" @click="open = false" wire:click="suspendAccess({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#475569] transition hover:bg-[#f1f5f9]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5v5M14.5 9.5v5" stroke-linecap="round"/></svg>
                Suspend Access
            </button>
        @elseif ($canReactivate)
            <button type="button" @click="open = false" wire:click="reactivate({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#16a34a] transition hover:bg-[#f0fdf4]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m10 9 5 3-5 3z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Reactivate Access
            </button>
        @endif

        @if ($canRecord)
            <button type="button" @click="open = false" wire:click="recordPayment({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20" stroke-linecap="round"/></svg>
                Record Payment
            </button>
        @endif

        <a href="{{ route('admin.gym.receipt', $m->id) }}" target="_blank" @click="open = false" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
            <svg class="size-[16px] text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            Download Receipt
        </a>

        @if ($canCancel)
            <div class="h-px bg-[#f1f1ee]"></div>
            <button type="button" @click="open = false" wire:click="cancelMembership({{ $m->id }})" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-[14px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">
                <svg class="size-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Cancel Membership
            </button>
        @endif
    </div>
</div>
