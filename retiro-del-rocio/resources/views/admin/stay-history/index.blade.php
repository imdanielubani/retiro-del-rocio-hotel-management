@php
    $quick = ['' => 'All time', 'today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month'];
@endphp

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

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative w-full sm:w-[260px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, room or ref…"
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

            {{-- Count + clear --}}
            <div class="ml-auto flex items-center gap-3">
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
                <p class="text-[12px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ number_format($stays->total()) }}</span> stays</p>
            </div>
        </div>

        {{-- Advanced filters (toggled, single line) --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[140px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Stays</option>
                        <option value="paid">Upcoming</option>
                        <option value="checked_in">In-House</option>
                        <option value="checked_out">Completed</option>
                    </select>
                </div>
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

    {{-- ===== Table / cards ===== --}}
    @if ($stays->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No stays found</p>
            <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try a different search or filter.' : 'Completed and active stays appear here.' }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-[#e5e7eb] bg-white">
            {{-- Desktop table --}}
            <table class="hidden w-full border-collapse text-left lg:table">
                <thead>
                    <tr>
                        @foreach (['Ref', 'Guest', 'Room', 'Check-in', 'Check-out', 'Nights', 'Amount', 'Status'] as $col)
                            <th class="border-b border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f1ee]">
                    @foreach ($stays as $b)
                        <tr wire:key="stay-{{ $b->id }}" class="transition hover:bg-[#f9fafb]">
                            <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $b->bookingCode() }}</td>
                            <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                <p class="text-[13px] text-[#1e1e1e]">{{ $b->room_name ?: '—' }}</p>
                                @if ($b->roomUnit)<p class="text-[11px] text-[#9ca3af]">Room {{ $b->roomUnit->number }}</p>@endif
                            </td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($b->check_in)->format('M j, Y') ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($b->check_out)->format('M j, Y') ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-md border border-[#e5e7eb] px-2 py-0.5 text-[11px] text-[#6b7280]">{{ $b->nights }}n</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#f1f1ee] lg:hidden">
                @foreach ($stays as $b)
                    <div wire:key="staym-{{ $b->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-medium text-[#f38c00]">{{ $b->bookingCode() }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                            <p class="text-[14px] font-bold text-[#1e1e1e]">{{ $b->amountLabel() }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $b->room_name ?: '—' }}</span>
                            <span>{{ optional($b->check_in)->format('M j') }} – {{ optional($b->check_out)->format('M j, Y') }} · {{ $b->nights }}n</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $stays->firstItem() }}–{{ $stays->lastItem() }} of {{ number_format($stays->total()) }} stays
                </p>
                @if ($stays->hasPages())
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($stays->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <span class="text-[12px] text-[#6b7280]">Page {{ $stays->currentPage() }} of {{ $stays->lastPage() }}</span>
                        <button type="button" wire:click="nextPage" @disabled(! $stays->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
