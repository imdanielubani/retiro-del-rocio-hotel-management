<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-7 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(20px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[220px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search room number…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'All', 'dirty' => 'Dirty', 'preparing' => 'Preparing', 'inspected' => 'Inspected', 'clean' => 'Clean', 'out_of_order' => 'Out of Order'] as $key => $label)
                    <button type="button" wire:click="$set('statusFilter', @js($key))"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $rooms->total() }}</span> rooms</p>
        </div>
    </div>

    {{-- ===== Rooms table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($rooms->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No rooms found</p>
                <p class="text-[13px] text-[#6b7280]">Try a different search or filter.</p>
            </div>
        @else
            <table class="w-full min-w-[760px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Room', 'Occupancy', 'Guest', 'Housekeeping Status', 'Updated', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $unit)
                        <tr wire:key="rm-{{ $unit->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5">
                                <div class="flex flex-col items-start gap-0.5">
                                    <span class="text-[13px] font-semibold text-[#1e1e1e]">Room {{ $unit->number }}</span>
                                    @if ($unit->room)
                                        <span class="text-[11px] text-[#9ca3af]">{{ $unit->room->name }}@if ($unit->room->type) · {{ $unit->room->type }}@endif</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $unit->statusBadge() }}">{{ $unit->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $unit->booking?->customer_name ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                @php [$fg, $bg] = $unit->housekeepingStatusColors(); @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $bg }}; color: {{ $fg }};">
                                    <span class="size-[5px] rounded-full" style="background: {{ $fg }}"></span>
                                    {{ $unit->housekeepingStatusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($unit->housekeeping_status_at)->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @include('admin.housekeeping.partials.room-status-menu', ['unit' => $unit])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $rooms->firstItem() ?? 0 }}–{{ $rooms->lastItem() ?? 0 }} of {{ number_format($rooms->total()) }} rooms</p>
                @if ($rooms->hasPages())
                    @php $last = $rooms->lastPage(); $cur = $rooms->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($rooms->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $rooms->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
