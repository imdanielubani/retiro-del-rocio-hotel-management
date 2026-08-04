<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-7 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(20px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 sm:flex-row sm:items-center sm:gap-3">
        <div class="relative w-full sm:w-[240px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search asset name…"
                   class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
        <button type="button" wire:click="toggleDueOnly"
                @class([
                    'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                    'border-[#dc2626] bg-[#dc2626] font-bold text-white' => $dueOnly,
                    'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => ! $dueOnly,
                ])>Service Due Only</button>
        <div class="flex-1"></div>
        <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $assets->total() }}</span> assets</p>
    </div>

    {{-- ===== Assets table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($assets->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No assets found</p>
                <p class="text-[13px] text-[#6b7280]">Try a different search or filter.</p>
            </div>
        @else
            <table class="w-full min-w-[820px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Asset', 'Category', 'Location', 'Interval', 'Last Serviced', 'Status', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assets as $asset)
                        <tr wire:key="as-{{ $asset->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $asset->name }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $asset->category ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $asset->locationLabel() }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $asset->service_interval_days ? 'Every '.$asset->service_interval_days.' days' : '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($asset->last_serviced_at)->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                @if ($asset->isDueForService())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#fee2e2] px-2.5 py-1 text-[11px] font-semibold text-[#dc2626]">
                                        <span class="size-[5px] rounded-full bg-[#dc2626]"></span> Service Due
                                    </span>
                                @elseif ($asset->service_interval_days)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#dcfce7] px-2.5 py-1 text-[11px] font-semibold text-[#16a34a]">On Schedule</span>
                                @else
                                    <span class="text-[12px] text-[#9ca3af]">No schedule</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @if ($asset->service_interval_days)
                                    <button type="button" wire:click="markServiced({{ $asset->id }})" wire:loading.attr="disabled"
                                            class="rounded-md border border-[#e5e7eb] bg-white px-3 py-1.5 text-[11px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Mark Serviced</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $assets->firstItem() ?? 0 }}–{{ $assets->lastItem() ?? 0 }} of {{ number_format($assets->total()) }} assets</p>
                @if ($assets->hasPages())
                    @php $last = $assets->lastPage(); $cur = $assets->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($assets->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $assets->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
