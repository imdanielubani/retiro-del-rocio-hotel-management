@php
    $statusIcon = [
        'calendar' => '<path d="M3 4h18v18H3zM3 9h18M8 2v4M16 2v4" />',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/>',
        'alert' => '<path d="M12 3 2 20h20L12 3zM12 10v4M12 17h.01" stroke-linecap="round" stroke-linejoin="round"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    ];
@endphp

<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="relative flex flex-col gap-1.5 rounded-2xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <span class="absolute right-5 top-5 flex size-7 items-center justify-center rounded-lg"
                      style="background: {{ $stat['accent'] }}1a; color: {{ $stat['accent'] }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">{!! $statusIcon[$stat['icon']] !!}</svg>
                </span>
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px] font-medium" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3 lg:flex-row lg:flex-wrap lg:items-center">
        {{-- Search --}}
        <div class="relative w-full lg:max-w-[280px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, ID or vehicle…"
                   class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-white pl-10 pr-4 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>

        {{-- Range tabs --}}
        <div class="no-scrollbar flex flex-nowrap items-center gap-1.5 overflow-x-auto">
            @foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month'] as $key => $label)
                <button type="button" wire:click="setRange('{{ $key }}')"
                        @class([
                            'flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 text-[13px] font-medium transition',
                            'bg-[#f38c00] text-white' => $range === $key,
                            'border border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                        ])>
                    @if ($key === 'today')
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                    @endif
                    {{ $label }}
                </button>
            @endforeach

            {{-- Filters toggle --}}
            <button type="button" wire:click="$toggle('showFilters')"
                    @class([
                        'flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border px-3 text-[13px] font-medium transition',
                        'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $showFilters || $status !== '',
                        'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => ! $showFilters && $status === '',
                    ])>
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
                Filters
            </button>
        </div>

        {{-- Result summary --}}
        <div class="flex items-center gap-3 text-[13px] text-[#6b7280] lg:ml-auto">
            <span><span class="font-semibold text-[#1e1e1e]">{{ $resultCount }}</span> transactions · <span class="font-semibold text-[#f38c00]">₦{{ number_format($resultSum) }}</span></span>
            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="shrink-0 rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-medium text-[#6b7280] hover:bg-[#f9fafb]">Clear all</button>
            @endif
        </div>

        {{-- Advanced filters (status) --}}
        @if ($showFilters)
            <div class="flex w-full flex-wrap items-center gap-2 border-t border-[#f1f1ee] pt-3">
                <span class="text-[12px] font-medium text-[#6b7280]">Status:</span>
                @foreach (['' => 'All', 'confirmed' => 'Confirmed', 'pending' => 'Pending', 'cancelled' => 'Cancelled'] as $key => $label)
                    <button type="button" wire:click="$set('status', '{{ $key }}')"
                            @class([
                                'rounded-full border px-3.5 py-1.5 text-[12px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] text-white' => $status === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $status !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Bookings table ===== --}}
    <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($bookings->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
                </span>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No transport bookings found</p>
                <p class="text-[13px] text-[#6b7280]">Vehicle pickup bookings made on the website will appear here.</p>
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[980px] text-left">
                    <thead>
                        <tr class="border-b border-[#f1f1ee] text-[11px] font-semibold uppercase tracking-[0.4px] text-[#9ca3af]">
                            <th class="px-5 py-3">Booking ID</th>
                            <th class="px-5 py-3">Guest</th>
                            <th class="px-5 py-3">Vehicle</th>
                            <th class="px-5 py-3">Route</th>
                            <th class="px-5 py-3">Date &amp; Time</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Payment</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            <tr class="border-b border-[#f6f6f4] last:border-b-0 hover:bg-[#fbfbfa]">
                                <td class="px-5 py-4"><span class="text-[13px] font-bold text-[#f38c00]">{{ $b->transportCode() }}</span></td>
                                <td class="px-5 py-4">
                                    <p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $b->customer_name ?: 'Guest' }}</p>
                                    <p class="text-[12px] text-[#9ca3af]">{{ $b->pickupPassengersLabel() }}</p>
                                </td>
                                <td class="px-5 py-4 text-[13px] text-[#374151]">{{ $b->pickup_vehicle }}</td>
                                <td class="px-5 py-4">
                                    <p class="text-[13px] text-[#1e1e1e]">{{ $b->pickupFrom() }}</p>
                                    <p class="text-[12px] text-[#9ca3af]">→ {{ $b->pickupTo() }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-[13px] text-[#1e1e1e]">{{ optional($b->pickup_arrival_date ?: $b->check_in)?->format('M j, Y') ?: '—' }}</p>
                                    <p class="flex items-center gap-1 text-[12px] text-[#9ca3af]">
                                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        {{ $b->pickup_time ?: '—' }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-[14px] font-bold text-[#1e1e1e]">{{ $b->pickupAmountLabel() }}</td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $b->paymentStatusBadge() }}">{{ $b->paymentStatusLabel() }}</span></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span></td>
                                <td class="px-5 py-4 text-right">
                                    @include('admin.vehicles._booking-actions', ['b' => $b])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#f1f1ee] lg:hidden">
                @foreach ($bookings as $b)
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-[12px] font-bold text-[#f38c00]">{{ $b->transportCode() }}</span>
                                <p class="text-[15px] font-semibold text-[#1e1e1e]">{{ $b->customer_name ?: 'Guest' }}</p>
                                <p class="text-[12px] text-[#9ca3af]">{{ $b->pickupPassengersLabel() }}</p>
                            </div>
                            @include('admin.vehicles._booking-actions', ['b' => $b])
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $b->paymentStatusBadge() }}">{{ $b->paymentStatusLabel() }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-[13px]">
                            <div><p class="text-[11px] text-[#9ca3af]">Vehicle</p><p class="text-[#1e1e1e]">{{ $b->pickup_vehicle }}</p></div>
                            <div><p class="text-[11px] text-[#9ca3af]">Amount</p><p class="font-bold text-[#1e1e1e]">{{ $b->pickupAmountLabel() }}</p></div>
                            <div><p class="text-[11px] text-[#9ca3af]">Date &amp; Time</p><p class="text-[#1e1e1e]">{{ optional($b->pickup_arrival_date ?: $b->check_in)?->format('M j, Y') ?: '—' }} · {{ $b->pickup_time ?: '—' }}</p></div>
                            <div><p class="text-[11px] text-[#9ca3af]">Route</p><p class="text-[#1e1e1e]">{{ $b->pickupFrom() }} → {{ $b->pickupTo() }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="flex flex-col items-center justify-between gap-3 border-t border-[#f1f1ee] px-5 py-3 sm:flex-row">
                <p class="text-[13px] text-[#6b7280]">Showing <span class="font-semibold text-[#1e1e1e]">{{ $bookings->firstItem() }}–{{ $bookings->lastItem() }}</span> of {{ $bookings->total() }}</p>
                <div>{{ $bookings->onEachSide(1)->links() }}</div>
            </div>
        @endif
    </div>

    {{-- ===== Detail modal ===== --}}
    @if ($selected)
        <div class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 px-4 py-8"
             x-data x-on:keydown.escape.window="$wire.closeModal()">
            <div class="absolute inset-0" wire:click="closeModal"></div>

            <div class="relative z-10 w-full max-w-[520px] rounded-2xl bg-white p-6 shadow-xl">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[1.5px] text-[#f38c00]">Transport Booking</p>
                        <h2 class="text-[20px] font-bold text-[#1e1e1e]">{{ $selected->transportCode() }}</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex rounded-full px-3 py-1 text-[12px] font-semibold {{ $selected->statusBadge() }}">{{ $selected->statusLabel() }}</span>
                        <button type="button" wire:click="closeModal" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3">
                    {{-- Guest --}}
                    <div class="flex items-center gap-3 rounded-xl bg-[#f9fafb] p-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#fff1e0] text-[14px] font-bold text-[#f38c00]">{{ $selected->pickupInitials() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-bold text-[#1e1e1e]">{{ $selected->customer_name ?: 'Guest' }}</p>
                            <p class="text-[12px] text-[#9ca3af]">{{ $selected->pickupPassengersLabel() }}</p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $selected->paymentStatusBadge() }}">{{ $selected->paymentStatusLabel() }}</span>
                    </div>

                    {{-- From --}}
                    <div class="flex items-center gap-3 rounded-xl border border-[#eef0ec] p-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#dcfce7] text-[#16a34a]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                        </span>
                        <div><p class="text-[10px] font-semibold uppercase tracking-[1px] text-[#9ca3af]">From</p><p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $selected->pickupFrom() }}</p></div>
                    </div>

                    {{-- To --}}
                    <div class="flex items-center gap-3 rounded-xl border border-[#eef0ec] p-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#fee2e2] text-[#dc2626]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                        </span>
                        <div><p class="text-[10px] font-semibold uppercase tracking-[1px] text-[#9ca3af]">To</p><p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $selected->pickupTo() }}</p></div>
                    </div>

                    {{-- Vehicle / Date / Time --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-[#eef0ec] p-3">
                            <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h14v-4" stroke-linecap="round"/></svg>
                                Vehicle</p>
                            <p class="mt-1 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->pickup_vehicle }}</p>
                        </div>
                        <div class="rounded-xl border border-[#eef0ec] p-3">
                            <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                                Date</p>
                            <p class="mt-1 text-[13px] font-semibold text-[#1e1e1e]">{{ optional($selected->pickup_arrival_date ?: $selected->check_in)?->format('M j, Y') ?: '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-[#eef0ec] p-3">
                            <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                                Time</p>
                            <p class="mt-1 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->pickup_time ?: '—' }}</p>
                        </div>
                    </div>

                    {{-- Flight / Bus number (when provided) — label depends on pickup type --}}
                    @if ($selected->pickup_flight_number)
                        <div class="rounded-xl border border-[#eef0ec] p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $selected->pickupNumberLabel() }}</p>
                            <p class="mt-1 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->pickup_flight_number }}</p>
                        </div>
                    @endif

                    {{-- Payment bar --}}
                    @php
                        $isPaid = in_array($selected->status, ['paid', 'checked_in', 'checked_out']);
                        $isRefunded = $selected->status === 'cancelled' && $selected->refund_status === 'completed';
                        $payBg = $isPaid ? 'bg-[#dcfce7] text-[#16a34a]' : ($isRefunded ? 'bg-[#ede9fe] text-[#7c3aed]' : ($selected->status === 'cancelled' ? 'bg-[#fee2e2] text-[#dc2626]' : 'bg-[#fef3c7] text-[#d97706]'));
                    @endphp
                    <div class="flex items-center justify-between rounded-xl px-4 py-4 {{ $payBg }}">
                        <span class="flex items-center gap-2 text-[14px] font-semibold">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20" stroke-linecap="round"/></svg>
                            {{ $selected->pickupPaymentLabel() }}
                        </span>
                        <span class="text-[18px] font-bold">{{ $selected->pickupAmountLabel() }}</span>
                    </div>

                    {{-- Footer actions (pending only) --}}
                    @if ($selected->status === 'pending')
                        <div class="mt-1 flex gap-3">
                            <button type="button" wire:click="reject({{ $selected->id }})"
                                    class="flex-1 rounded-xl bg-[#fde2e2] px-4 py-3 text-[14px] font-bold text-[#dc2626] transition hover:bg-[#fbd0d0]">Reject Booking</button>
                            <button type="button" wire:click="confirm({{ $selected->id }})"
                                    class="flex-1 rounded-xl bg-[#f38c00] px-4 py-3 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Confirm Booking</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
