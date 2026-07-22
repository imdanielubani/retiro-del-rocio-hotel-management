@php
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: '—';
    $hasFilters = $search || $status || $range || $from || $to;
@endphp

{{-- 20s poll keeps the register live as visitors are verified at the gate. --}}
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
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('pass', $filteredCount) }}</p>
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
                        <option value="pending">Pending</option>
                        <option value="inside">Inside</option>
                        <option value="exited">Exited</option>
                        <option value="denied">Denied</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
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
        @if ($passes->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No visitor passes found</p>
                <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try adjusting your filters.' : 'Passes guests issue from their tablets will appear here.' }}</p>
            </div>
        @else
            <div class="hidden lg:block">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Pass No','Visitor / Host','Room','Codes','Status','Entry','Issued','Actions'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] {{ $col === 'Actions' ? 'text-right' : '' }}">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($passes as $p)
                            @php [$tc, $bg] = $p->adminStatusColors(); @endphp
                            <tr wire:key="vp-{{ $p->id }}" wire:click="view({{ $p->id }})" class="cursor-pointer border-b border-[#e5e7eb] transition hover:bg-[#f3f4f6] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 font-mono text-[12px] text-[#6b7280]">{{ $p->caseNumber() }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[11px] font-bold text-[#6b7280]">{{ $initials($p->visitor_name) }}</div>
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $p->visitor_name }}</p>
                                            <p class="truncate text-[11px] text-[#9ca3af]">Host: {{ $p->host_name ?: '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $p->room_number ? 'Room '.$p->room_number : '—' }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-col gap-0.5 font-mono text-[12px] leading-tight">
                                        @if ($p->online_code)
                                            <span class="text-[#16a34a]"><span class="font-sans text-[10px] text-[#9ca3af]">ON</span> {{ $p->online_code }}</span>
                                        @endif
                                        <span class="text-[#6b7280]"><span class="font-sans text-[10px] text-[#9ca3af]">OFF</span> {{ $p->code }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $bg }}; color: {{ $tc }};">{{ $p->adminStatusLabel() }}</span></td>
                                <td class="px-4 py-3.5">
                                    @if ($p->verified_via)
                                        <span class="inline-flex items-center gap-1.5 text-[12px] text-[#374151]">
                                            @if ($p->verified_via === 'lock')
                                                <svg class="size-3.5 text-[#16a34a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                            @else
                                                <svg class="size-3.5 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 16h8"/></svg>
                                            @endif
                                            {{ $p->entryMethodLabel() }}
                                        </span>
                                    @else
                                        <span class="text-[12px] text-[#9ca3af]">{{ $p->ttlockLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ optional($p->created_at)->format('M j, g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3.5" wire:click.stop>
                                    @include('admin.security._visitor-pass-actions', ['p' => $p])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile / narrow cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] lg:hidden">
                @foreach ($passes as $p)
                    @php [$tc, $bg] = $p->adminStatusColors(); @endphp
                    <div wire:key="vp-m-{{ $p->id }}" wire:click="view({{ $p->id }})" class="flex cursor-pointer flex-col gap-2 px-4 py-3.5 active:bg-[#f9fafb]">
                        <div class="flex items-center justify-between">
                            <span class="text-[14px] font-medium text-[#1e1e1e]">{{ $p->visitor_name }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold" style="background: {{ $bg }}; color: {{ $tc }};">{{ $p->adminStatusLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $p->room_number ? 'Room '.$p->room_number : '—' }} · Host: {{ $p->host_name ?: '—' }}</span>
                            <span class="font-mono">{{ $p->caseNumber() }}</span>
                        </div>
                        <div class="flex items-center justify-between font-mono text-[12px]">
                            <span>@if ($p->online_code)<span class="text-[#16a34a]">ON {{ $p->online_code }}</span> · @endif<span class="text-[#6b7280]">OFF {{ $p->code }}</span></span>
                            <span class="font-sans text-[11px] text-[#9ca3af]">{{ $p->verified_via ? $p->entryMethodLabel() : $p->ttlockLabel() }}</span>
                        </div>
                        <div class="flex justify-end pt-1" wire:click.stop>
                            @include('admin.security._visitor-pass-actions', ['p' => $p])
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $passes->count() }} of {{ number_format($passes->total()) }} passes</p>
                @if ($passes->hasPages())
                    @php $last=$passes->lastPage();$cur=$passes->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($passes->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $passes->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Detail drawer ===== --}}
    @if ($selected)
        @php
            [$stc, $sbg] = $selected->adminStatusColors();
            $events = collect([
                ['Issued', $selected->created_at, 'Pass created'],
                ['Verified', $selected->verified_at, $selected->verified_via ? 'via '.$selected->entryMethodLabel().(optional($selected->handledBy)->name ? ' · '.$selected->handledBy->name : '') : null],
                ['Exited', $selected->exited_at, 'Visitor left'],
                ['Denied', $selected->denied_at, optional($selected->handledBy)->name ? 'by '.$selected->handledBy->name : 'Turned away'],
                ['Cancelled', $selected->cancelled_at, 'Pass revoked'],
            ])->filter(fn ($e) => $e[1] !== null)->sortBy(fn ($e) => $e[1])->values();
        @endphp
        <div class="fixed inset-0 z-40" wire:key="vp-drawer-{{ $selected->id }}">
            <div class="absolute inset-0 bg-black/30" wire:click="closeDetail"></div>
            <div class="absolute right-0 top-0 flex h-full w-full max-w-[420px] flex-col bg-white shadow-2xl">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-3 border-b border-[#e5e7eb] px-6 py-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[13px] font-bold text-[#6b7280]">{{ $initials($selected->visitor_name) }}</div>
                        <div class="min-w-0">
                            <p class="truncate text-[16px] font-semibold text-[#1e1e1e]">{{ $selected->visitor_name }}</p>
                            <p class="font-mono text-[12px] text-[#9ca3af]">{{ $selected->caseNumber() }}</p>
                        </div>
                    </div>
                    <button wire:click="closeDetail" class="rounded-lg p-1.5 text-[#9ca3af] transition hover:bg-[#f3f4f6]">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <span class="inline-flex rounded-full px-3 py-1 text-[12px] font-semibold" style="background: {{ $sbg }}; color: {{ $stc }};">{{ $selected->adminStatusLabel() }}</span>

                    {{-- Codes --}}
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                            <p class="text-[10px] uppercase tracking-[0.5px] text-[#9ca3af]">Online code</p>
                            <p class="mt-1 font-mono text-[18px] font-bold tracking-[2px] {{ $selected->online_code ? 'text-[#16a34a]' : 'text-[#d1d5db]' }}">{{ $selected->online_code ?: '——————' }}</p>
                        </div>
                        <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                            <p class="text-[10px] uppercase tracking-[0.5px] text-[#9ca3af]">Offline code</p>
                            <p class="mt-1 font-mono text-[18px] font-bold tracking-[2px] text-[#1e1e1e]">{{ $selected->code ?: '——————' }}</p>
                        </div>
                    </div>

                    {{-- Details --}}
                    <dl class="mt-5 divide-y divide-[#f1f1ee] rounded-xl border border-[#e5e7eb]">
                        @foreach ([
                            ['Host', $selected->host_name ?: '—'],
                            ['Room', $selected->room_number ? 'Room '.$selected->room_number : '—'],
                            ['Suite', $selected->suite_name ?: '—'],
                            ['Reservation', optional($selected->booking)->reference ?: '—'],
                            ['TTLock', $selected->ttlockLabel()],
                            ['Email', $selected->visitor_email ?: '—'],
                            ['WhatsApp', $selected->visitor_phone ?: '—'],
                            ['Valid until', optional($selected->expires_at)->format('M j, g:i A') ?: '—'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                                <dt class="text-[12px] text-[#6b7280]">{{ $label }}</dt>
                                <dd class="truncate text-[13px] font-medium text-[#1e1e1e]">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- Timeline --}}
                    <p class="mb-3 mt-6 text-[11px] font-bold uppercase tracking-[1px] text-[#9ca3af]">Timeline</p>
                    <ol class="relative ml-1 border-l border-[#e5e7eb]">
                        @foreach ($events as [$label, $time, $sub])
                            <li class="mb-4 ml-4 last:mb-0">
                                <span class="absolute -left-[5px] mt-1 size-2.5 rounded-full {{ $label === 'Verified' ? 'bg-[#16a34a]' : ($label === 'Denied' ? 'bg-[#dc2626]' : 'bg-[#f38c00]') }}"></span>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[13px] font-semibold text-[#1e1e1e]">{{ $label }}</p>
                                    <p class="shrink-0 text-[12px] text-[#9ca3af]">{{ $time->format('M j, g:i A') }}</p>
                                </div>
                                @if ($sub)
                                    <p class="text-[12px] text-[#6b7280]">{{ $sub }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Footer actions --}}
                @if ($selected->isOpen() || $selected->isInside())
                    <div class="flex flex-wrap items-center gap-2 border-t border-[#e5e7eb] bg-[#f9fafb] px-6 py-4">
                        @if ($selected->isInside())
                            <button wire:click="markExited({{ $selected->id }})" class="rounded-lg border border-[#e5e7eb] bg-white px-3.5 py-2 text-[12px] font-medium text-[#374151] hover:bg-[#f3f4f6]">Mark exited</button>
                        @endif
                        @if ($selected->isOpen())
                            @if (in_array($selected->ttlock_status, ['offline', 'failed']))
                                <button wire:click="retryTtlock({{ $selected->id }})" class="rounded-lg border border-[#e5e7eb] bg-white px-3.5 py-2 text-[12px] font-medium text-[#374151] hover:bg-[#f3f4f6]">Retry TTLock</button>
                            @endif
                            @if ($selected->visitor_email)
                                <button wire:click="resendEmail({{ $selected->id }})" class="rounded-lg border border-[#e5e7eb] bg-white px-3.5 py-2 text-[12px] font-medium text-[#374151] hover:bg-[#f3f4f6]">Re-send email</button>
                            @endif
                            <button wire:click="deny({{ $selected->id }})" wire:confirm="Deny this visitor entry?" class="rounded-lg border border-[#fde68a] bg-white px-3.5 py-2 text-[12px] font-medium text-[#b45309] hover:bg-[#fffbeb]">Deny</button>
                            <button wire:click="revoke({{ $selected->id }})" wire:confirm="Revoke this visitor pass?" class="rounded-lg border border-[#fee2e2] bg-white px-3.5 py-2 text-[12px] font-medium text-[#dc2626] hover:bg-[#fef2f2]">Revoke</button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
