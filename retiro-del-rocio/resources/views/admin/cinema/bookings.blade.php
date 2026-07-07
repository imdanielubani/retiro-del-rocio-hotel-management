@php
    $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
@endphp

<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }">
    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filter bar --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, ID or movie…"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="$set('range','')" @class(['rounded-lg border px-3.5 py-[7px] text-[12px] transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === '', 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== ''])>All time</button>
                @foreach (['today'=>'Today','7d'=>'Next 7 days','30d'=>'Next 30 days','month'=>'This month'] as $k => $l)
                    <button type="button" wire:click="setRange('{{ $k }}')" @class(['rounded-lg border px-3.5 py-[7px] text-[12px] transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === $k, 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $k])>{{ $l }}</button>
                @endforeach
            </div>
            <button type="button" @click="showFilters = !showFilters" :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'" class="flex items-center gap-2 rounded-lg border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
            </button>
            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('booking', $filteredCount) }}</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll" class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear all
                    </button>
                @endif
                <button type="button" wire:click="openCreate" class="flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Add Booking
                </button>
            </div>
        </div>
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Movie</label>
                    <select wire:model.live="movieFilter" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Movies</option>@foreach ($movies as $mv)<option value="{{ $mv->id }}">{{ $mv->title }}</option>@endforeach
                    </select>
                </div>
                <div class="flex min-w-[110px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Year</label>
                    <select wire:model.live="year" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Years</option>@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                    </select>
                </div>
                <div class="flex min-w-[120px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Month</label>
                    <select wire:model.live="month" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Months</option>@foreach ($months as $num => $name)<option value="{{ $num }}">{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="flex min-w-[240px] flex-[2] flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Custom Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="from" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <span class="text-[#9ca3af]">→</span>
                        <input type="date" wire:model.live="to" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                    </div>
                </div>
                <div class="flex min-w-[130px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option><option value="confirmed">Confirmed</option><option value="used">Used</option><option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex min-w-[130px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Payment</label>
                    <select wire:model.live="payment" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Payments</option><option value="paid">Paid</option><option value="pending">Pending</option><option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($bookings->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No bookings found</p>
                <p class="text-[13px] text-[#6b7280]">Movie ticket bookings made on the website will appear here.</p>
            </div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1040px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Ticket ID','Guest','Movie','Room','Date & Time','Amount','Payment','Status','Action'] as $col)
                                <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]','text-right' => $col === 'Action'])>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $b)
                            @php [$sC,$sB]=$b->statusColors(); [$pC,$pB]=$b->paymentColors(); @endphp
                            <tr wire:key="cb-{{ $b->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#f38c00]">{{ $b->code }}</td>
                                <td class="px-4 py-3.5"><p class="text-[13px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p><p class="text-[11px] text-[#9ca3af]">{{ $b->customer_email }}</p></td>
                                <td class="px-4 py-3.5"><p class="max-w-[180px] truncate text-[13px] text-[#374151]">{{ $b->movie_title }}</p><p class="text-[11px] text-[#9ca3af]">{{ $b->guestsLabel() }}</p></td>
                                <td class="px-4 py-3.5 text-[12px] text-[#374151]">{{ $b->roomLabel() }}</td>
                                <td class="px-4 py-3.5 text-[12px] text-[#374151]">{{ optional($b->show_date)->format('M j, Y') }}<span class="block text-[11px] text-[#9ca3af]">{{ $b->show_time }}</span></td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $pB }}; color: {{ $pC }};">{{ $b->paymentLabel() }}</span></td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $b->statusLabel() }}</span></td>
                                <td class="px-4 py-3.5 text-right">@include('admin.cinema.partials.booking-menu', ['b' => $b])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($bookings as $b)
                    @php [$sC,$sB]=$b->statusColors(); [$pC,$pB]=$b->paymentColors(); @endphp
                    <div wire:key="cb-m-{{ $b->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-[#f38c00]">{{ $b->code }}</span>
                            <div class="flex items-center gap-1.5">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $pB }}; color: {{ $pC }};">{{ $b->paymentLabel() }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $b->statusLabel() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between"><p class="text-[14px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p><p class="text-[14px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</p></div>
                        <p class="truncate text-[12px] text-[#6b7280]">{{ $b->movie_title }} · {{ $b->roomLabel() }}</p>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]"><span>{{ optional($b->show_date)->format('M j') }} · {{ $b->show_time }}</span><span>{{ $b->guestsLabel() }}</span></div>
                        <div class="flex justify-end pt-1">@include('admin.cinema.partials.booking-menu', ['b' => $b])</div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $bookings->count() }} of {{ number_format($bookings->total()) }} bookings</p>
                @if ($bookings->hasPages())
                    @php $last=$bookings->lastPage();$cur=$bookings->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($bookings->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $bookings->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('admin.cinema.partials.booking-detail')
    @include('admin.cinema.partials.booking-create')
</div>
