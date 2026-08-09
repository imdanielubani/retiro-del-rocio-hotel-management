<div class="flex flex-col gap-4">
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
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="relative w-full sm:w-[240px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search order, guest…"
                   class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
        <select wire:model.live="status" class="h-[34px] rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            <option value="">All Status</option>
            @foreach (['pending'=>'Pending','confirmed'=>'Confirmed','preparing'=>'Preparing','ready'=>'Ready','on_way'=>'On the Way','delivered'=>'Delivered','cancelled'=>'Cancelled'] as $k => $l)
                <option value="{{ $k }}">{{ $l }}</option>
            @endforeach
        </select>
        <select wire:model.live="payment" class="h-[34px] rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            <option value="">All Payments</option>
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
        </select>
        <div class="ml-auto flex items-center gap-3">
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('order', $filteredCount) }}</p>
            @if ($hasFilters)
                <button type="button" wire:click="clearAll" class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                    <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear all
                </button>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($orders->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No orders found</p>
                <p class="text-[13px] text-[#6b7280]">Drink orders placed from the guest tablet will appear here.</p>
            </div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1000px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Order ID','Guest','Items','Placed At','Amount','Payment','Status','Action'] as $col)
                                <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]','text-right' => $col === 'Action'])>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $o)
                            @php [$sC,$sB]=$o->statusColors(); @endphp
                            <tr wire:key="do-{{ $o->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#f38c00]">{{ $o->orderCode() }}</td>
                                <td class="px-4 py-3.5"><p class="text-[13px] font-medium text-[#1e1e1e]">{{ $o->customer_name ?: '—' }}</p><p class="text-[11px] text-[#9ca3af]">{{ $o->customer_email }}</p></td>
                                <td class="px-4 py-3.5"><p class="max-w-[220px] truncate text-[13px] text-[#374151]">{{ $o->itemsLabel() }}</p><p class="text-[11px] text-[#9ca3af]">{{ $o->item_count }} {{ \Illuminate\Support\Str::plural('item', $o->item_count) }}</p></td>
                                <td class="px-4 py-3.5 text-[12px] text-[#374151]">{{ optional($o->created_at)->format('M j, Y') }}<span class="block text-[11px] text-[#9ca3af]">{{ optional($o->created_at)->format('g:i A') }}</span></td>
                                <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $o->totalLabel() }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $o->paymentStatusBadge() }}">{{ $o->paymentLabel() }}</span></td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $o->statusLabel() }}</span></td>
                                <td class="px-4 py-3.5 text-right">@include('admin.bar-lounge.partials.order-menu', ['o' => $o])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($orders as $o)
                    @php [$sC,$sB]=$o->statusColors(); @endphp
                    <div wire:key="do-m-{{ $o->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-[#f38c00]">{{ $o->orderCode() }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $o->statusLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between"><p class="text-[14px] font-medium text-[#1e1e1e]">{{ $o->customer_name ?: '—' }}</p><p class="text-[14px] font-bold text-[#1e1e1e]">{{ $o->totalLabel() }}</p></div>
                        <p class="truncate text-[12px] text-[#6b7280]">{{ $o->itemsLabel() }}</p>
                        <div class="flex justify-end pt-1">@include('admin.bar-lounge.partials.order-menu', ['o' => $o])</div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $orders->count() }} of {{ number_format($orders->total()) }} orders</p>
                @if ($orders->hasPages())
                    @php $last=$orders->lastPage();$cur=$orders->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($orders->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $orders->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('admin.bar-lounge.partials.order-detail')
</div>
