@php
    $tabs = [
        'all' => ['label' => 'All Bookings', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'],
        'calendar' => ['label' => 'Calendar', 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z'],
        'approval' => ['label' => 'Approval Queue', 'icon' => 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
        'cancellations' => ['label' => 'Cancellations', 'icon' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM15 9l-6 6M9 9l6 6'],
    ];
    $quick = ['' => 'All time', 'today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month'];
    $hasFilters = $search || $range || $status || $roomFilter || $from || $to;
@endphp

<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }">
    {{-- ===== Tabs (segmented control: one white container) ===== --}}
    <div class="no-scrollbar flex w-fit max-w-full items-center gap-1 overflow-x-auto rounded-[10px] border border-[#e5e7eb] bg-white p-1.5">
        @foreach ($tabs as $key => $t)
            <button type="button" wire:click="setTab('{{ $key }}')"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-[7px] px-3.5 py-[7px] text-[12px] transition',
                        'bg-[#f38c00] font-medium text-white' => $tab === $key,
                        'font-normal text-[#6b7280] hover:bg-[#f9fafb]' => $tab !== $key,
                    ])>
                <svg class="size-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $t['icon'] }}"/></svg>
                {{ $t['label'] }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'approval')
        @php
            $initials = fn ($name) => collect(explode(' ', trim((string) $name)))->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: '—';
            $dateRange = function ($ci, $co) {
                if (! $ci || ! $co) return '—';
                return $ci->isSameMonth($co) ? $ci->format('M j').'–'.$co->format('j, Y') : $ci->format('M j').' – '.$co->format('M j, Y');
            };
        @endphp

        {{-- ===== Approval stat cards ===== --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($approvalStats as $stat)
                <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                     style="border-left-color: {{ $stat['accent'] }}">
                    <div class="flex items-start justify-between">
                        <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                        @if ($stat['clock'])
                            <svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        @endif
                    </div>
                    <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                    <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ===== Master / detail ===== --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,360px)_1fr]">
            {{-- Pending list --}}
            <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-4 py-3">
                    <p class="text-[14px] font-bold text-[#1e1e1e]">Pending Requests ({{ $pendingRequests->count() }})</p>
                </div>
                @if ($pendingRequests->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-4 py-16 text-center">
                        <div class="flex size-11 items-center justify-center rounded-full bg-[#f3f3ee]">
                            <svg class="size-5 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </div>
                        <p class="text-[14px] font-semibold text-[#1e1e1e]">All caught up</p>
                        <p class="text-[12px] text-[#6b7280]">No bookings are awaiting review.</p>
                    </div>
                @else
                    <div class="no-scrollbar flex max-h-[640px] flex-col divide-y divide-[#f1f1ee] overflow-y-auto">
                        @foreach ($pendingRequests as $r)
                            @php $isSel = $approvalSelected && $approvalSelected->id === $r->id; @endphp
                            <div wire:key="pr-{{ $r->id }}" wire:click="selectApproval({{ $r->id }})"
                                 class="flex cursor-pointer gap-3 border-l-[3px] px-4 py-3.5 transition {{ $isSel ? 'border-[#f38c00] bg-[#fff7ed]' : 'border-transparent hover:bg-[#f9fafb]' }}">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[12px] font-bold text-[#6b7280]">{{ $initials($r->customer_name) }}</div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $r->customer_name ?: '—' }}</p>
                                        <span class="shrink-0 text-[11px] text-[#9ca3af]">{{ $r->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="truncate text-[12px] text-[#6b7280]">{{ $r->room_name ?: '—' }}</p>
                                    <p class="text-[12px] text-[#6b7280]">{{ $dateRange($r->check_in, $r->check_out) }}</p>
                                    <div class="mt-1.5 flex items-center justify-between">
                                        <span class="text-[13px] font-bold text-[#f38c00]">{{ $r->amountLabel() }}</span>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" wire:click.stop="approve({{ $r->id }})" title="Approve"
                                                    class="flex size-7 items-center justify-center rounded-md bg-[#dcfce7] text-[#16a34a] transition hover:bg-[#bbf7d0]">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            </button>
                                            <button type="button" wire:click.stop="decline({{ $r->id }})" title="Decline"
                                                    class="flex size-7 items-center justify-center rounded-md bg-[#fee2e2] text-[#dc2626] transition hover:bg-[#fecaca]">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Detail --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                @if (! $approvalSelected)
                    <div class="flex h-full min-h-[320px] flex-col items-center justify-center gap-2 text-center">
                        <p class="text-[15px] font-semibold text-[#1e1e1e]">No request selected</p>
                        <p class="text-[13px] text-[#6b7280]">Pick a pending request from the list to review it.</p>
                    </div>
                @else
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[18px] font-bold text-[#1e1e1e]">{{ $approvalSelected->bookingCode() }} — {{ $approvalSelected->customer_name ?: '—' }}</p>
                            <p class="mt-1 text-[13px] text-[#6b7280]">{{ $approvalSelected->room_name ?: '—' }} · {{ $dateRange($approvalSelected->check_in, $approvalSelected->check_out) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-[#fef3c7] px-3 py-1 text-[12px] font-medium text-[#d97706]">Awaiting Review</span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-y-5 border-t border-[#e5e7eb] pt-5 sm:grid-cols-2">
                        @php
                            $fields = [
                                'Booking ID' => $approvalSelected->bookingCode(),
                                'Guest Name' => $approvalSelected->customer_name ?: '—',
                                'Room' => $approvalSelected->room_name ?: '—',
                                'Dates' => $dateRange($approvalSelected->check_in, $approvalSelected->check_out),
                                'Amount' => $approvalSelected->amountLabel(),
                                'Submitted' => $approvalSelected->created_at->diffForHumans(),
                            ];
                        @endphp
                        @foreach ($fields as $label => $value)
                            <div>
                                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $label }}</p>
                                <p class="mt-1 text-[14px] font-medium text-[#1e1e1e]">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] p-4">
                        <p class="text-[13px] font-semibold text-[#1e1e1e]">Special Requests</p>
                        <p class="mt-1 text-[13px] leading-relaxed text-[#6b7280]">No special requests were captured for this booking.</p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="approve({{ $approvalSelected->id }})"
                                class="flex items-center justify-center gap-2 rounded-xl bg-[#16a34a] py-3 text-[14px] font-bold text-white transition hover:bg-[#15803d]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            Approve Booking
                        </button>
                        <button type="button" wire:click="decline({{ $approvalSelected->id }})"
                                class="flex items-center justify-center gap-2 rounded-xl border border-[#fecaca] bg-[#fef2f2] py-3 text-[14px] font-bold text-[#dc2626] transition hover:bg-[#fee2e2]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            Decline
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @elseif ($tab === 'cancellations')
        {{-- ===== Cancellation stat cards ===== --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cancelStats as $stat)
                <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                     style="border-left-color: {{ $stat['accent'] }}">
                    <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                    <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                    <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ===== Log + Refund processor ===== --}}
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_minmax(0,360px)]">
            {{-- Cancellation Log --}}
            <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-5 py-4">
                    <p class="text-[15px] font-medium text-[#1e1e1e]">Cancellation Log</p>
                </div>

                @if ($bookings->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                            <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                        </div>
                        <p class="text-[15px] font-semibold text-[#1e1e1e]">No cancellations</p>
                        <p class="text-[13px] text-[#6b7280]">Cancelled bookings will appear here for refund processing.</p>
                    </div>
                @else
                    {{-- Desktop table --}}
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-[#fafafa]">
                                    @foreach (['Can. ID', 'Booking', 'Guest', 'Room', 'Amount', 'Refund', 'Date', 'Status'] as $col)
                                        <th class="border-b border-[#e5e7eb] px-4 py-3 text-[10px] uppercase tracking-[0.6px] text-[#6b7280]">{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bookings as $b)
                                    @php $isSel = $cancelSelected && $cancelSelected->id === $b->id; @endphp
                                    <tr wire:key="can-{{ $b->id }}" wire:click="selectCancellation({{ $b->id }})"
                                        class="cursor-pointer border-b border-[#f1f1ee] transition {{ $isSel ? 'bg-[#fff7ed]' : ($loop->even ? 'bg-[#f9fafb] hover:bg-[#f3f4f6]' : 'bg-white hover:bg-[#f9fafb]') }}">
                                        <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $b->cancellationCode() }}</td>
                                        <td class="px-4 py-3.5 text-[13px] text-[#6b7280]">{{ $b->bookingCode() }}</td>
                                        <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</td>
                                        <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $b->room_name ?: '—' }}</td>
                                        <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</td>
                                        <td class="px-4 py-3.5 text-[13px] font-semibold">
                                            @if ($b->refund_status === 'declined')
                                                <span class="text-[#dc2626]">₦0</span>
                                            @elseif ($b->refund_amount)
                                                <span class="text-[#16a34a]">{{ $b->refundLabel() }}</span>
                                            @else
                                                <span class="text-[#9ca3af]">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($b->updated_at)->format('M j, Y') }}</td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->refundStatusBadge() }}">{{ $b->refundStatusLabel() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="flex flex-col divide-y divide-[#f1f1ee] lg:hidden">
                        @foreach ($bookings as $b)
                            @php $isSel = $cancelSelected && $cancelSelected->id === $b->id; @endphp
                            <button type="button" wire:key="canm-{{ $b->id }}" wire:click="selectCancellation({{ $b->id }})"
                                    class="flex flex-col gap-1.5 px-4 py-3.5 text-left {{ $isSel ? 'bg-[#fff7ed]' : '' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-[13px] font-medium text-[#f38c00]">{{ $b->cancellationCode() }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->refundStatusBadge() }}">{{ $b->refundStatusLabel() }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                                    <p class="text-[13px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</p>
                                </div>
                                <p class="text-[12px] text-[#6b7280]">{{ $b->room_name }} · {{ optional($b->updated_at)->format('M j, Y') }}</p>
                            </button>
                        @endforeach
                    </div>

                    {{-- Footer / pagination --}}
                    <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-[12px] text-[#6b7280]">Showing {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} of {{ number_format($bookings->total()) }} cancellations</p>
                        @if ($bookings->hasPages())
                            @php $last = $bookings->lastPage(); $cur = $bookings->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="previousPage" @disabled($bookings->onFirstPage())
                                        class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                </button>
                                @for ($p = $start; $p <= $end; $p++)
                                    <button type="button" wire:click="gotoPage({{ $p }})"
                                            @class([
                                                'flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition',
                                                'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur,
                                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur,
                                            ])>{{ $p }}</button>
                                @endfor
                                <button type="button" wire:click="nextPage" @disabled(! $bookings->hasMorePages())
                                        class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Refund processor + policy reference --}}
            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Refund Processor</p>
                    @if (! $cancelSelected)
                        <p class="mt-3 text-[13px] text-[#6b7280]">Select a cancellation from the log to process its refund.</p>
                    @else
                        <div class="mt-4 flex flex-col gap-4">
                            <div>
                                <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Cancellation Policy</label>
                                <select wire:model.live="refundPolicy" class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-white px-3 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                                    <option value="">Choose a policy…</option>
                                    <option value="flexible">Flexible — 100%</option>
                                    <option value="moderate">Moderate — 80%</option>
                                    <option value="strict">Strict — 50%</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Refund Amount</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[14px] text-[#6b7280]">₦</span>
                                    <input type="number" min="0" wire:model="refundAmount" class="h-10 w-full rounded-lg border border-[#e5e7eb] pl-7 pr-3 text-[14px] font-semibold text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                                </div>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <label class="text-[13px] font-medium text-[#374151]">Refund Method</label>
                                    <span class="rounded-full bg-[#f3f4f6] px-2.5 py-0.5 text-[11px] font-medium text-[#6b7280]">Paid via {{ $cancelSelected->methodLabel() }}</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    @foreach (['bank_transfer' => 'Bank Transfer', 'card_reversal' => 'Card Reversal', 'hotel_credit' => 'Hotel Credit'] as $val => $label)
                                        <label class="flex cursor-pointer items-center gap-2.5 text-[13px] text-[#374151]">
                                            <input type="radio" wire:model="refundMethod" value="{{ $val }}" class="size-4 accent-[#f38c00]">
                                            {{ $label }}
                                            @if ($val === $cancelSelected->defaultRefundMethod())
                                                <span class="text-[11px] text-[#16a34a]">· matches payment</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <button type="button" wire:click="issueRefund({{ $cancelSelected->id }})"
                                        class="rounded-xl bg-[#16a34a] py-2.5 text-[14px] font-bold text-white transition hover:bg-[#15803d]">Issue Refund</button>
                                <button type="button" wire:click="declineRefund({{ $cancelSelected->id }})"
                                        class="rounded-xl border border-[#fecaca] bg-[#fef2f2] py-2.5 text-[14px] font-bold text-[#dc2626] transition hover:bg-[#fee2e2]">Decline</button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Policy Reference --}}
                <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Policy Reference</p>
                    <div class="mt-3 flex flex-col divide-y divide-[#f1f1ee]">
                        @foreach ([['Flexible', '100% refund', 'Full refund if cancelled 24 hours before check-in'], ['Moderate', '80% refund', 'Full refund if cancelled 72 hours before check-in'], ['Strict', '50% refund', '50% refund if cancelled 7 days before check-in']] as [$name, $pill, $desc])
                            <div class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $name }}</p>
                                    <span class="rounded-full bg-[#fff3e0] px-2.5 py-0.5 text-[11px] font-semibold text-[#b45309]">{{ $pill }}</span>
                                </div>
                                <p class="mt-1 text-[12px] text-[#6b7280]">{{ $desc }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative w-full sm:w-[260px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, ID or room…"
                       class="h-10 w-full rounded-full border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            <div class="hidden h-7 w-px bg-[#e5e7eb] sm:block"></div>

            {{-- Quick date ranges --}}
            <div class="flex flex-wrap items-center gap-[5px]">
                @foreach ($quick as $key => $label)
                    <button type="button" wire:click="{{ $key === '' ? "\$set('range', '')" : "setRange('$key')" }}"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-[#222a1f]' => $range === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="hidden h-7 w-px bg-[#e5e7eb] sm:block"></div>

            {{-- Filters toggle --}}
            <button type="button" @click="showFilters = !showFilters"
                    :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                    class="flex items-center gap-1.5 rounded-[8px] border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                Filters
            </button>

            {{-- Count + clear + new booking --}}
            <div class="ml-auto flex items-center gap-3">
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
                <p class="text-[12px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $bookingsCount }}</span> bookings</p>
                <button type="button" wire:click="openCreate"
                        class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-[8px] bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New Booking
                </button>
            </div>
        </div>

        {{-- Advanced filters (toggled, single line) --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                @if ($tab === 'all')
                    <div class="flex min-w-[140px] flex-1 flex-col gap-1.5">
                        <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                        <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                            <option value="">All Status</option>
                            <option value="paid">Confirmed</option>
                            <option value="pending">Pending</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                @endif
                <div class="flex min-w-[160px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Room</label>
                    <select wire:model.live="roomFilter" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Rooms</option>
                        @foreach ($rooms as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex min-w-[240px] flex-[2] flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Check-in range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="from" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <span class="text-[#9ca3af]">→</span>
                        <input type="date" wire:model.live="to" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Calendar view ===== --}}
    @if ($calendar)
        <div class="rounded-2xl border border-[#e5e7eb] bg-white p-4 sm:p-5">
            {{-- Header: nav + view toggle + legend --}}
            <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="calPrev" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] transition hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <p class="min-w-[150px] text-center text-[16px] font-bold text-[#1e1e1e]">{{ $calendar['title'] }}</p>
                    <button type="button" wire:click="calNext" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] transition hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>

                {{-- Month / Week / Day toggle --}}
                <div class="flex items-center gap-1 self-start rounded-[10px] bg-[#f3f3ee] p-1 xl:self-auto">
                    @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $key => $label)
                        <button type="button" wire:click="setCalView('{{ $key }}')"
                                @class([
                                    'rounded-[7px] px-4 py-1.5 text-[13px] font-semibold transition',
                                    'bg-[#f38c00] text-white' => $calendar['view'] === $key,
                                    'text-[#6b7280] hover:text-[#1e1e1e]' => $calendar['view'] !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                    @foreach (['Confirmed' => '#16a34a', 'Pending' => '#d97706', 'Checked In' => '#7c3aed', 'Cancelled' => '#dc2626'] as $label => $color)
                        <span class="flex items-center gap-1.5 text-[12px] text-[#6b7280]">
                            <span class="size-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $label }}
                        </span>
                    @endforeach
                </div>
            </div>

            @if ($calendar['view'] === 'day')
                {{-- Day view: list of that day's bookings --}}
                @php $day = $calendar['days'][0]; @endphp
                @if ($day['bookings']->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[#d6d9d2] py-16 text-center">
                        <p class="text-[14px] font-semibold text-[#1e1e1e]">No bookings on this day</p>
                        <p class="text-[12px] text-[#6b7280]">Use the arrows to browse other days.</p>
                    </div>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach ($day['bookings'] as $b)
                            <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate wire:key="cald-{{ $b->id }}"
                                    class="flex items-center justify-between gap-3 rounded-xl border-l-4 border border-[#e5e7eb] bg-white px-4 py-3 text-left transition hover:bg-[#f9fafb]"
                                    style="border-left-color: {{ $b->statusColor() }}">
                                <div class="min-w-0">
                                    <p class="truncate text-[14px] font-semibold text-[#1e1e1e]">{{ $b->room_name ?: '—' }}</p>
                                    <p class="truncate text-[12px] text-[#6b7280]">{{ $b->bookingCode() }} · {{ $b->customer_name ?: '—' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</span>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                {{-- Month / Week grid --}}
                <div class="no-scrollbar overflow-x-auto">
                    <div class="grid min-w-[760px] grid-cols-7 gap-px overflow-hidden rounded-xl border border-[#e5e7eb] bg-[#e5e7eb]">
                        @foreach (['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'] as $i => $dow)
                            <div class="py-2 text-center text-[11px] font-semibold uppercase tracking-wide text-[#d97706] {{ ($i === 0 || $i === 6) ? 'bg-[#fdf6e9]' : 'bg-white' }}">{{ $dow }}</div>
                        @endforeach
                        @foreach ($calendar['days'] as $day)
                            <div class="flex min-h-[116px] flex-col p-1.5 {{ $day['isWeekend'] ? 'bg-[#fdfaf2]' : 'bg-white' }}">
                                <span @class([
                                    'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-[12px]',
                                    'bg-[#f38c00] font-bold text-white' => $day['isToday'],
                                    'font-medium text-[#1e1e1e]' => ! $day['isToday'] && $day['inMonth'],
                                    'text-[#c7c7c7]' => ! $day['inMonth'],
                                ])>{{ $day['date']->day }}</span>
                                <div class="mt-1 flex flex-col gap-1">
                                    @foreach ($day['bookings']->take(2) as $b)
                                        <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate wire:key="calm-{{ $b->id }}"
                                                class="block w-full truncate rounded px-1.5 py-0.5 text-left text-[11px] font-medium text-white"
                                                style="background: {{ $b->statusColor() }}" title="{{ $b->room_name }} · {{ $b->statusLabel() }}">
                                            {{ $b->room_name ?: $b->bookingCode() }}
                                        </a>
                                    @endforeach
                                    @if ($day['bookings']->count() > 2)
                                        <span class="px-1 text-[10px] text-[#9ca3af]">+{{ $day['bookings']->count() - 2 }} more</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @else
        {{-- ===== Table / cards ===== --}}
        @if ($bookings->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                    <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No bookings found</p>
                <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try a different search or filter.' : 'Paid reservations from checkout will appear here.' }}</p>
            </div>
        @else
            {{-- Table + list + pagination all in ONE card (matches design) --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white">
                {{-- Desktop table --}}
                <table class="hidden w-full border-collapse text-left xl:table">
                    <thead>
                        <tr>
                            @foreach (['Booking', 'Guest', 'Room', 'Check-in', 'Check-out', 'Amount', 'Nights', 'Status', 'Action'] as $col)
                                <th class="border-b border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl {{ $col === 'Action' ? 'text-right' : '' }}">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f1f1ee]">
                        @foreach ($bookings as $b)
                            <tr wire:key="bk-{{ $b->id }}" class="transition hover:bg-[#f9fafb]">
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $b->bookingCode() }}</td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $b->room_name ?: '—' }}</p>
                                    @if ($b->room?->type)
                                        <p class="text-[12px] text-[#9ca3af]">{{ $b->room->type }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($b->check_in)->format('M j, Y') ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($b->check_out)->format('M j, Y') ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-md border border-[#e5e7eb] px-2 py-0.5 text-[11px] text-[#6b7280]">{{ $b->nights }}n</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3.5">@include('admin.bookings._actions', ['b' => $b])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Mobile / tablet list (same card) --}}
                <div class="flex flex-col divide-y divide-[#f1f1ee] xl:hidden">
                    @foreach ($bookings as $b)
                        <div wire:key="bkm-{{ $b->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[13px] font-medium text-[#f38c00]">{{ $b->bookingCode() }}</span>
                                <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                                @include('admin.bookings._actions', ['b' => $b])
                            </div>
                        </div>
                        <p class="mt-2 text-[13px] text-[#374151]">{{ $b->room_name ?: '—' }}@if ($b->room?->type)<span class="text-[#9ca3af]"> · {{ $b->room->type }}</span>@endif</p>
                        <div class="mt-2 flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ optional($b->check_in)->format('M j') }} – {{ optional($b->check_out)->format('M j, Y') }} · {{ $b->nights }}n</span>
                            <span class="font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Footer / pagination (inside the same card) --}}
                <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} of {{ number_format($bookings->total()) }} bookings
                </p>
                @if ($bookings->hasPages())
                    @php
                        $last = $bookings->lastPage();
                        $cur = $bookings->currentPage();
                        $start = max(1, min($cur - 1, $last - 2));
                        $end = min($last, $start + 2);
                    @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($bookings->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class([
                                        'flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition',
                                        'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur,
                                        'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur,
                                    ])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $bookings->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
                </div>
            </div>
        @endif
    @endif

    @endif

    {{-- ===================== New Booking modal ===================== --}}
    <div x-data="{ show: @entangle('showCreate') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[680px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Apartments</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">New Booking</h2>
                    <p class="mt-0.5 text-[12px] text-[#6b7280]">Create a reservation manually (walk-in or phone booking).</p>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="createBooking" class="mt-5 flex flex-col gap-4"
                  x-data="{ today: @js(now()->toDateString()), checkIn: @entangle('cCheckIn').live, checkOut: @entangle('cCheckOut').live }">
                {{-- Guest name + phone --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Guest Name</label>
                        <input type="text" wire:model="cName" placeholder="e.g. Micheal Philip"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Phone <span class="font-normal normal-case text-[#9ca3af]">(optional)</span></label>
                        <input type="text" wire:model="cPhone" placeholder="070 1234 5678"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cPhone') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Email --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Email <span class="font-normal normal-case text-[#9ca3af]">(optional)</span></label>
                    <input type="email" wire:model="cEmail" placeholder="guest@email.com"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('cEmail') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                {{-- Room --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Room / Apartment</label>
                    <select wire:model.live="cRoomId"
                            class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        <option value="">Select a room…</option>
                        @foreach ($roomOptions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }} — ₦{{ number_format($r->price) }}/night</option>
                        @endforeach
                    </select>
                    @error('cRoomId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                {{-- Dates + guests --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Check-in</label>
                        <input type="date" x-model="checkIn" :min="today"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cCheckIn') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Check-out</label>
                        <input type="date" x-model="checkOut" :min="checkIn || today"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cCheckOut') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Guests</label>
                        <input type="number" min="1" max="30" wire:model="cGuests"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cGuests') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Amount + status --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Amount (₦)</label>
                        <div class="flex h-11 items-center rounded-xl border border-[#e5e7eb] bg-white px-3.5 focus-within:border-[#f38c00] focus-within:ring-2 focus-within:ring-[#f38c00]/15">
                            <span class="mr-2 text-[15px] font-semibold text-[#9ca3af]">₦</span>
                            <input type="number" min="0" wire:model="cAmount" placeholder="Auto"
                                   class="h-full w-full bg-transparent text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none">
                        </div>
                        <span class="text-[10px] text-[#9ca3af]">Blank = room price × nights</span>
                        @error('cAmount') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Status</label>
                        <select wire:model="cStatus"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="paid">Confirmed (paid)</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false"
                            class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit"
                            class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Create Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
