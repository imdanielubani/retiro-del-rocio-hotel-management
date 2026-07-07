@php
    $quickFilters = [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month',
        'last_month' => 'Last month',
    ];
    $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
@endphp

<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }" wire:poll.15s>
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <div class="flex items-start justify-between">
                    <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                </div>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Filter bar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, ID, booking…"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            <div class="hidden h-7 w-px bg-[#e5e7eb] sm:block"></div>

            {{-- Quick range filters --}}
            <div class="flex flex-wrap items-center gap-[5px]">
                @foreach ($quickFilters as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-[#222a1f]' => $range === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                            ])>
                        {{ $label }}
                    </button>
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

            {{-- Summary + clear --}}
            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]">
                    <span class="font-bold text-[#1e1e1e]">{{ $summaryCount }}</span> transactions ·
                    <span class="font-bold text-[#f38c00]">{{ $summaryAmount }}</span>
                </p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
            </div>
        </div>

        {{-- Advanced filters (toggled) — all fields stay on one line --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                {{-- Year --}}
                <div class="flex min-w-[110px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Year</label>
                    <select wire:model.live="year" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Years</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Month --}}
                <div class="flex min-w-[120px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Month</label>
                    <select wire:model.live="month" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Months</option>
                        @foreach ($months as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Day --}}
                <div class="flex min-w-[100px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Day</label>
                    <select wire:model.live="day" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">Any Days</option>
                        @for ($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </div>
                {{-- Custom range --}}
                <div class="flex min-w-[240px] flex-[2] flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Custom Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="from" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <span class="text-[#9ca3af]">→</span>
                        <input type="date" wire:model.live="to" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                    </div>
                </div>
                {{-- Payment method --}}
                <div class="flex min-w-[140px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Payment Method</label>
                    <select wire:model.live="method" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Methods</option>
                        @foreach ($methods as $m)
                            <option value="{{ $m }}">{{ ucwords(str_replace('_', ' ', $m)) }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Status --}}
                <div class="flex min-w-[120px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Refunded / Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Transactions table ===== --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        {{-- Card header --}}
        <div class="flex flex-col gap-3 border-b border-[#e5e7eb] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[15px] font-medium text-[#1e1e1e]">Transactions</p>
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-3.5 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search transactions..."
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-3 text-[13px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
        </div>

        @if ($transactions->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                    <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20" stroke-linecap="round"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No transactions found</p>
                <p class="text-[13px] text-[#6b7280]">Payments captured at checkout will appear here.</p>
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[900px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Transaction ID', 'Booking', 'Guest', 'Amount', 'Method', 'Date', 'Status'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            <tr wire:key="txn-{{ $t->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $t->txnId() }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#6b7280]">
                                    <span class="inline-flex items-center gap-1.5">
                                        {{ $t->bookingCode() }}
                                        <span @class([
                                            'rounded-full px-1.5 py-0.5 text-[10px] font-semibold',
                                            'bg-[#f3e8ff] text-[#7c3aed]' => $t->sourceLabel() === 'Spa',
                                            'bg-[#fff7ed] text-[#c2620a]' => $t->sourceLabel() === 'Gym',
                                            'bg-[#fef2f2] text-[#b91c1c]' => $t->sourceLabel() === 'Restaurant',
                                            'bg-[#fef9c3] text-[#a16207]' => $t->sourceLabel() === 'Cinema',
                                            'bg-[#e0f2fe] text-[#0369a1]' => ! in_array($t->sourceLabel(), ['Spa', 'Gym', 'Restaurant', 'Cinema'], true),
                                        ])>{{ $t->sourceLabel() }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#1e1e1e]">{{ $t->customer_name ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $t->amountLabel() }}</td>
                                <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $t->methodLabel() }}</td>
                                <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($t->paid_at ?? $t->created_at)->format('M j, Y') }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $t->paymentStatusBadge() }}">{{ $t->paymentStatusLabel() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($transactions as $t)
                    <div wire:key="txn-m-{{ $t->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-medium text-[#f38c00]">{{ $t->txnId() }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $t->paymentStatusBadge() }}">{{ $t->paymentStatusLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-medium text-[#1e1e1e]">{{ $t->customer_name ?: '—' }}</p>
                            <p class="text-[14px] font-bold text-[#1e1e1e]">{{ $t->amountLabel() }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $t->bookingCode() }} · {{ $t->sourceLabel() }} · {{ $t->methodLabel() }}</span>
                            <span>{{ optional($t->paid_at ?? $t->created_at)->format('M j, Y') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $transactions->count() }} of {{ number_format($transactions->total()) }} transactions
                </p>
                @if ($transactions->hasPages())
                    @php
                        $last = $transactions->lastPage();
                        $cur = $transactions->currentPage();
                        $start = max(1, min($cur - 1, $last - 2));
                        $end = min($last, $start + 2);
                    @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($transactions->onFirstPage())
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
                        <button type="button" wire:click="nextPage" @disabled(! $transactions->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
