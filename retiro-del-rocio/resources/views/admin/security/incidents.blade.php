@php
    $badge = [
        'active' => ['#dc2626', '#fee2e2', 'Unacknowledged'],
        'acknowledged' => ['#d97706', '#fef3c7', 'Acknowledged'],
        'resolved' => ['#16a34a', '#dcfce7', 'Resolved'],
        'cancelled' => ['#6b7280', '#f3f4f6', 'Cancelled'],
    ];
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: '—';
    $hasFilters = $search || $status || $range || $from || $to;
@endphp

{{-- 20s poll is the backstop; the `admin` echo channel updates it live the moment an SOS changes. --}}
<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }" wire:poll.20s>

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

    {{-- Live: open incidents strip (only when something is open) --}}
    @if ($openIncidents->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-[#fecaca] bg-[#fef2f2]">
            <div class="flex items-center gap-2 border-b border-[#fecaca] px-5 py-3">
                <span class="relative flex size-2.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-[#dc2626] opacity-60"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-[#dc2626]"></span>
                </span>
                <p class="text-[12px] font-bold uppercase tracking-[1px] text-[#dc2626]">Live · Open Incidents ({{ $openIncidents->count() }})</p>
            </div>
            <div class="flex flex-col divide-y divide-[#fee2e2]">
                @foreach ($openIncidents as $i)
                    @php [$tc, $bg, $lbl] = $badge[$i->status] ?? $badge['active']; @endphp
                    <div wire:key="open-{{ $i->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 text-[#dc2626]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                            <span class="text-[14px] font-bold text-[#1e1e1e]">{{ $i->room_number ? 'Room '.$i->room_number : 'Guest room' }}</span>
                        </div>
                        <span class="text-[13px] text-[#6b7280]">{{ $i->guest_name ?: 'Guest' }}{{ $i->suite_name ? ' · '.$i->suite_name : '' }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium" style="background: {{ $bg }}; color: {{ $tc }};">{{ $lbl }}</span>
                        @if ($i->status === 'acknowledged' && $i->acknowledgedBy)
                            <span class="text-[12px] text-[#16a34a]">{{ $i->acknowledgedBy->name }} responding</span>
                        @endif
                        <span class="ml-auto font-mono text-[12px] text-[#dc2626]">{{ $i->caseNumber() }}</span>
                        <span class="text-[12px] text-[#9ca3af]">{{ optional($i->raised_at)->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filter bar --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search room, guest or case no…"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="$set('range','')" @class(['rounded-lg border px-3.5 py-[7px] text-[12px] transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === '', 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== ''])>All time</button>
                @foreach (['today'=>'Today','7d'=>'7 days','30d'=>'30 days','month'=>'This month'] as $k => $l)
                    <button type="button" wire:click="setRange('{{ $k }}')" @class(['rounded-lg border px-3.5 py-[7px] text-[12px] transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $range === $k, 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $k])>{{ $l }}</button>
                @endforeach
            </div>
            <button type="button" @click="showFilters = !showFilters" :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'" class="flex items-center gap-2 rounded-lg border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
            </button>
            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('incident', $filteredCount) }}</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll" class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>Clear all
                    </button>
                @endif
                <button type="button" wire:click="export" class="flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-lg border border-[#e5e7eb] bg-white px-4 text-[12px] font-bold text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Export CSV
                </button>
            </div>
        </div>
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="status" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option>
                        <option value="active">Unacknowledged</option>
                        <option value="acknowledged">Acknowledged</option>
                        <option value="resolved">Resolved</option>
                        <option value="cancelled">Cancelled</option>
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
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($incidents->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No incidents found</p>
                <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try adjusting your filters.' : 'SOS alerts raised from guest tablets will appear here.' }}</p>
            </div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[1000px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Case No','Room / Guest','Status','Raised','Response','Officer','Resolution'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $a)
                            @php [$tc, $bg, $lbl] = $badge[$a->status] ?? $badge['active']; @endphp
                            <tr wire:key="inc-{{ $a->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 font-mono text-[12px] {{ $a->status === 'active' ? 'font-bold text-[#dc2626]' : 'text-[#6b7280]' }}">{{ $a->caseNumber() }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[11px] font-bold text-[#6b7280]">{{ $initials($a->guest_name) }}</div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $a->room_number ? 'Room '.$a->room_number : 'Guest room' }}</p>
                                            <p class="truncate text-[11px] text-[#9ca3af]">{{ $a->guest_name ?: 'Guest' }}{{ $a->suite_name ? ' · '.$a->suite_name : '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $bg }}; color: {{ $tc }};">{{ $lbl }}</span></td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ optional($a->raised_at)->format('M j, g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] {{ $a->responseSeconds() !== null ? 'font-medium text-[#16a34a]' : 'text-[#9ca3af]' }}">{{ $a->responseLabel() }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ optional($a->acknowledgedBy)->name ?? ($a->status === 'cancelled' ? 'Stood down' : '—') }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $a->resolutionLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($incidents as $a)
                    @php [$tc, $bg, $lbl] = $badge[$a->status] ?? $badge['active']; @endphp
                    <div wire:key="inc-m-{{ $a->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[12px] {{ $a->status === 'active' ? 'font-bold text-[#dc2626]' : 'text-[#6b7280]' }}">{{ $a->caseNumber() }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold" style="background: {{ $bg }}; color: {{ $tc }};">{{ $lbl }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-medium text-[#1e1e1e]">{{ $a->room_number ? 'Room '.$a->room_number : 'Guest room' }}</p>
                            <p class="text-[12px] {{ $a->responseSeconds() !== null ? 'text-[#16a34a]' : 'text-[#9ca3af]' }}">{{ $a->responseLabel() }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $a->guest_name ?: 'Guest' }}{{ $a->suite_name ? ' · '.$a->suite_name : '' }}</span>
                            <span>{{ optional($a->raised_at)->format('M j, g:i A') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $incidents->count() }} of {{ number_format($incidents->total()) }} incidents</p>
                @if ($incidents->hasPages())
                    @php $last=$incidents->lastPage();$cur=$incidents->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($incidents->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $incidents->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
