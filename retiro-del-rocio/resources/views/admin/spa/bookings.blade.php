@php
    $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

    // Resolve a booking's category {name,color} and total duration using the
    // stored JSON first, falling back to the live service metadata map.
    $bkCategory = function ($b) use ($serviceMeta) {
        foreach (($b->services ?? []) as $svc) {
            $name = $svc['category'] ?? ($serviceMeta[$svc['slug'] ?? '']['category'] ?? null);
            if ($name) {
                $color = $svc['category_color'] ?? ($serviceMeta[$svc['slug'] ?? '']['color'] ?? '#6b7280');
                return ['name' => $name, 'color' => $color];
            }
        }
        return null;
    };
    $bkDuration = function ($b) use ($serviceMeta) {
        $sum = 0;
        foreach (($b->services ?? []) as $svc) {
            $sum += (int) ($svc['duration_minutes'] ?? ($serviceMeta[$svc['slug'] ?? '']['duration'] ?? 0));
        }
        return $sum;
    };
@endphp

<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }">
    {{-- ===== Stat cards (consistent with the other admin modules) ===== --}}
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

    {{-- ===== Filter bar (mirrors the Payments module) ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search for guest"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Quick range filters --}}
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="$set('range', '')"
                        @class([
                            'rounded-lg border px-3.5 py-[7px] text-[12px] transition',
                            'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === '',
                            'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== '',
                        ])>All time</button>
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')"
                            @class([
                                'rounded-lg border px-3.5 py-[7px] text-[12px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            {{-- Filters toggle --}}
            <button type="button" @click="showFilters = !showFilters"
                    :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                    class="flex items-center gap-2 rounded-lg border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
            </button>

            {{-- Summary + clear + add --}}
            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('session', $filteredCount) }}</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
                <button type="button" wire:click="openCreate"
                        class="flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-[8px] bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add Booking
                </button>
            </div>
        </div>

        {{-- Advanced filters (toggled) --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[110px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Year</label>
                    <select wire:model.live="year" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Years</option>
                        @foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                    </select>
                </div>
                <div class="flex min-w-[120px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Month</label>
                    <select wire:model.live="month" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Months</option>
                        @foreach ($months as $num => $name)<option value="{{ $num }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="flex min-w-[100px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Day</label>
                    <select wire:model.live="day" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">Any Days</option>
                        @for ($d = 1; $d <= 31; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor
                    </select>
                </div>
                <div class="flex min-w-[240px] flex-[2] flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Custom Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="from" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <span class="text-[#9ca3af]">→</span>
                        <input type="date" wire:model.live="to" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                    </div>
                </div>
                <div class="flex min-w-[130px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex min-w-[130px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Payment</label>
                    <select wire:model.live="payment" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Payments</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Sessions table ===== --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($bookings->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                    <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M6 21V10m4 11V10m4 11V10m4 11V10M4 10l8-6 8 6"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No sessions found</p>
                <p class="text-[13px] text-[#6b7280]">Spa bookings made on the website will appear here.</p>
            </div>
        @else
            {{-- Table (tablet + desktop; scrolls horizontally on tablet) --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1040px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Booking ID', 'Guest', 'Service', 'Category', 'Date & Time', 'Duration', 'Amount', 'Payment', 'Status', 'Action'] as $col)
                                <th @class([
                                        'border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]',
                                        'text-right' => $col === 'Action',
                                    ])>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            @php
                                [$sColor, $sBg] = $b->statusColors();
                                [$pColor, $pBg] = $b->paymentColors();
                                $cat = $bkCategory($b);
                                $dur = $bkDuration($b);
                            @endphp
                            <tr wire:key="spabk-{{ $b->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#f38c00]">{{ $b->sessionCode() }}</td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                                    <p class="text-[11px] text-[#9ca3af]">{{ $b->customer_email ?: '' }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $b->primaryService() }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($cat)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.4px]" style="background: {{ $cat['color'] }}1a; color: {{ $cat['color'] }};">{{ $cat['name'] }}</span>
                                    @else
                                        <span class="text-[12px] text-[#9ca3af]">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] text-[#1e1e1e]">{{ $b->date?->format('M j, Y') ?: '—' }}</p>
                                    @if ($b->time)
                                        <p class="flex items-center gap-1 text-[11px] text-[#9ca3af]">
                                            <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            {{ $b->time }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $dur ? $dur.' min' : '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->totalLabel() }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $pBg }}; color: {{ $pColor }};">{{ $b->paymentLabel() }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $sBg }}; color: {{ $sColor }};">{{ $b->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @include('admin.spa.partials.booking-menu', ['b' => $b])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards (phones only) --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($bookings as $b)
                    @php
                        [$sColor, $sBg] = $b->statusColors();
                        [$pColor, $pBg] = $b->paymentColors();
                        $cat = $bkCategory($b);
                        $dur = $bkDuration($b);
                    @endphp
                    <div wire:key="spabk-m-{{ $b->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-[#f38c00]">{{ $b->sessionCode() }}</span>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $pBg }}; color: {{ $pColor }};">{{ $b->paymentLabel() }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $sBg }}; color: {{ $sColor }};">{{ $b->statusLabel() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                            <p class="text-[14px] font-bold text-[#1e1e1e]">{{ $b->totalLabel() }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $b->primaryService() }}{{ $cat ? ' · '.$cat['name'] : '' }}</span>
                            <span>{{ $b->date?->format('M j, Y') ?: '—' }}{{ $b->time ? ' · '.$b->time : '' }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[12px] text-[#9ca3af]">{{ $dur ? $dur.' min' : '' }}</span>
                            @include('admin.spa.partials.booking-menu', ['b' => $b])
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $bookings->count() }} of {{ number_format($bookings->total()) }} sessions</p>
                @if ($bookings->hasPages())
                    @php $last = $bookings->lastPage(); $cur = $bookings->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($bookings->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
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

    {{-- ===== Detail slide-over ===== --}}
    @include('admin.spa.partials.booking-detail')

    {{-- ===== Add Booking modal ===== --}}
    @include('admin.spa.partials.booking-create')
</div>
