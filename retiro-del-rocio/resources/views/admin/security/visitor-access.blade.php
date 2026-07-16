@php
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: '—';
    $hasFilters = $search || $method || $presence || $range || $from || $to;
    // [text, background] for the outcome pill.
    $outcome = fn ($p) => match ($p->accessOutcomeLabel()) {
        'Lock' => ['#16a34a', '#dcfce7'],
        'Keypad' => ['#2563eb', '#dbeafe'],
        'Denied' => ['#dc2626', '#fee2e2'],
        default => ['#6b7280', '#f3f4f6'],
    };
@endphp

{{-- 20s poll keeps the log live as visitors enter and leave. --}}
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

    {{-- Filter bar --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search visitor, host, room or code…"
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
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('entry', $filteredCount) }}</p>
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
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Outcome</label>
                    <select wire:model.live="method" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Outcomes</option>
                        <option value="lock">Lock (self-service)</option>
                        <option value="keypad">Keypad (officer)</option>
                        <option value="denied">Denied</option>
                    </select>
                </div>
                <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Presence</label>
                    <select wire:model.live="presence" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">Anyone</option>
                        <option value="inside">Still inside</option>
                        <option value="exited">Left</option>
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
        @if ($entries->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No access records found</p>
                <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try adjusting your filters.' : 'Gate entries and denials will appear here once visitors are verified.' }}</p>
            </div>
        @else
            <div class="hidden lg:block">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Visitor / Host','Room','Outcome','Officer','Entered','Exited','Time inside','Actions'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] {{ $col === 'Actions' ? 'text-right' : '' }}">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $p)
                            @php [$otc, $obg] = $outcome($p); @endphp
                            <tr wire:key="va-{{ $p->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[11px] font-bold text-[#6b7280]">{{ $initials($p->visitor_name) }}</div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $p->visitor_name }}</p>
                                            <p class="truncate text-[11px] text-[#9ca3af]">Host: {{ $p->host_name ?: '—' }} · {{ $p->caseNumber() }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $p->room_number ? 'Room '.$p->room_number : '—' }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $obg }}; color: {{ $otc }};">{{ $p->accessOutcomeLabel() }}</span></td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ optional($p->handledBy)->name ?? ($p->verified_via === 'lock' ? 'Self · lock' : '—') }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ optional($p->verified_at)->format('M j, g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px]">
                                    @if ($p->isInside())
                                        <span class="inline-flex items-center gap-1.5 text-[#16a34a]"><span class="size-1.5 rounded-full bg-[#16a34a]"></span>Inside</span>
                                    @else
                                        <span class="text-[#374151]">{{ optional($p->exited_at)->format('M j, g:i A') ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-[13px] {{ $p->isInside() ? 'font-medium text-[#16a34a]' : 'text-[#6b7280]' }}">{{ $p->verified_at ? $p->timeInsideLabel() : '—' }}</td>
                                <td class="px-4 py-3.5">
                                    @include('admin.security._visitor-access-actions', ['p' => $p])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile / narrow cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] lg:hidden">
                @foreach ($entries as $p)
                    @php [$otc, $obg] = $outcome($p); @endphp
                    <div wire:key="va-m-{{ $p->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[14px] font-medium text-[#1e1e1e]">{{ $p->visitor_name }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold" style="background: {{ $obg }}; color: {{ $otc }};">{{ $p->accessOutcomeLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $p->room_number ? 'Room '.$p->room_number : '—' }} · Host: {{ $p->host_name ?: '—' }}</span>
                            <span>{{ optional($p->verified_at)->format('M j, g:i A') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[12px]">
                            <span class="{{ $p->isInside() ? 'font-medium text-[#16a34a]' : 'text-[#6b7280]' }}">{{ $p->isInside() ? 'Inside · '.$p->timeInsideLabel() : 'Left '.optional($p->exited_at)->format('g:i A') }}</span>
                            @if ($p->isInside())
                                @include('admin.security._visitor-access-actions', ['p' => $p])
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $entries->count() }} of {{ number_format($entries->total()) }} entries</p>
                @if ($entries->hasPages())
                    @php $last=$entries->lastPage();$cur=$entries->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($entries->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $entries->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
