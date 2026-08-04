<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }">
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

    {{-- ===== Filter bar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest or room"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Category pills --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['' => 'All', 'housekeeping' => 'Housekeeping', 'maintenance' => 'Maintenance'] as $key => $label)
                    <button type="button" wire:click="$set('category', @js($key))"
                            @class([
                                'rounded-lg border px-3.5 py-[7px] text-[12px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $category === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $category !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="hidden h-6 w-px bg-[#e5e7eb] sm:block"></div>

            {{-- Quick range --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['' => 'All time', 'today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month'] as $key => $label)
                    <button type="button" wire:click="{{ $key === '' ? '$set(\'range\', \'\')' : "setRange('{$key}')" }}"
                            @class([
                                'rounded-lg border px-3.5 py-[7px] text-[12px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <button type="button" @click="showFilters = !showFilters"
                    :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                    class="flex items-center gap-2 rounded-lg border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
            </button>

            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('request', $filteredCount) }}</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
                <button type="button" wire:click="export"
                        class="flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-[8px] border border-[#e5e7eb] px-4 text-[12px] font-bold text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Export CSV
                </button>
            </div>
        </div>

        {{-- Advanced filters --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[130px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="completed">Completed</option>
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
            </div>
        </div>
    </div>

    {{-- ===== Requests table ===== --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($requests->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                    <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No requests found</p>
                <p class="text-[13px] text-[#6b7280]">Housekeeping asks and maintenance faults raised from a guest tablet will appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1020px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Category', 'Request', 'Guest', 'Room', 'Status', 'Completed By', 'Raised'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $r)
                            <tr wire:key="req-{{ $r['uid'] }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.4px]"
                                          style="background: {{ $r['category'] === 'housekeeping' ? '#3b82f61a' : '#7c3aed1a' }}; color: {{ $r['category'] === 'housekeeping' ? '#3b82f6' : '#7c3aed' }};">
                                        {{ $r['category_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $r['title'] }}</p>
                                    @if ($r['notes'])
                                        <p class="max-w-[280px] truncate text-[11px] text-[#9ca3af]">{{ $r['notes'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $r['guest_name'] ?: '—' }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($r['room_number'])
                                        <div class="flex flex-col items-start gap-0.5">
                                            <span class="text-[13px] font-medium text-[#374151]">Room {{ $r['room_number'] }}</span>
                                            @if ($r['room_name'])
                                                <span class="text-[11px] text-[#9ca3af]">{{ $r['room_name'] }}@if ($r['room_category']) · {{ $r['room_category'] }}@endif</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[13px] text-[#374151]">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold"
                                          style="background: {{ $r['is_open'] ? '#fef3c7' : '#dcfce7' }}; color: {{ $r['is_open'] ? '#d97706' : '#16a34a' }};">
                                        {{ $r['status_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $r['completed_by_name'] ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $r['created_at']?->format('M j, Y · g:ia') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ number_format($requests->total()) }} requests</p>
                @if ($requests->hasPages())
                    @php $last = $requests->lastPage(); $cur = $requests->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($requests->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $requests->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
