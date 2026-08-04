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
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 sm:flex-row sm:items-center sm:gap-3">
        <div class="relative w-full sm:w-[240px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search part name…"
                   class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach (['' => 'All', 'pending' => 'Pending', 'fulfilled' => 'Fulfilled', 'denied' => 'Denied'] as $key => $label)
                <button type="button" wire:click="$set('statusFilter', @js($key))"
                        @class([
                            'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                            'border-[#f38c00] bg-[#f38c00] font-bold text-white' => $statusFilter === $key,
                            'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex-1"></div>
        <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $requests->total() }}</span> requests</p>
    </div>

    {{-- ===== Requests table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($requests->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No parts requests found</p>
                <p class="text-[13px] text-[#6b7280]">Try a different search or filter.</p>
            </div>
        @else
            <table class="w-full min-w-[900px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Part', 'Qty', 'Work Order', 'Requested By', 'Status', 'Requested', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr wire:key="pr-{{ $request->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $request->part_name }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $request->quantity }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $request->workOrder?->title ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $request->requested_by ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badge = match ($request->status) {
                                        'fulfilled' => ['bg' => '#dcfce7', 'fg' => '#16a34a'],
                                        'denied' => ['bg' => '#fee2e2', 'fg' => '#dc2626'],
                                        default => ['bg' => '#fef3c7', 'fg' => '#d97706'],
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $badge['bg'] }}; color: {{ $badge['fg'] }};">{{ $request->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($request->created_at)->diffForHumans() }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @if ($request->isPending())
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" wire:click="deny({{ $request->id }})" wire:loading.attr="disabled"
                                                class="rounded-md border border-[#fecaca] bg-white px-3 py-1.5 text-[11px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">Deny</button>
                                        <button type="button" wire:click="fulfill({{ $request->id }})" wire:loading.attr="disabled"
                                                class="rounded-md border border-[#16a34a] bg-[#16a34a] px-3 py-1.5 text-[11px] font-medium text-white transition hover:bg-[#15803d]">Fulfil</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

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
