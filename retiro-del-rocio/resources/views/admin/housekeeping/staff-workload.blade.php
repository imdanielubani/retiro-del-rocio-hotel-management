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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search staff name…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'housekeeping' => 'Housekeeping', 'maintenance' => 'Maintenance'] as $key => $label)
                    <button type="button" wire:click="$set('roleFilter', @js($key))"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-white' => $roleFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $roleFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="hidden h-6 w-px bg-[#e5e7eb] sm:block"></div>

            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'All time', 'today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month'] as $key => $label)
                    <button type="button" wire:click="{{ $key === '' ? '$set(\'range\', \'\')' : "setRange('{$key}')" }}"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-white' => $range === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $range !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $rows->total() }}</span> staff</p>
        </div>
    </div>

    {{-- ===== Staff table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No staff found</p>
                <p class="text-[13px] text-[#6b7280]">Housekeeping and maintenance staff will appear here once assigned that role.</p>
            </div>
        @else
            <table class="w-full min-w-[720px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Staff', 'Role', 'Completed', 'Currently Assigned', 'Items Logged'] as $col)
                            <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr wire:key="sw-{{ $r['role'] }}-{{ $r['id'] }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $r['name'] }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.4px]"
                                      style="background: {{ $r['role'] === 'housekeeping' ? '#3b82f61a' : '#7c3aed1a' }}; color: {{ $r['role'] === 'housekeeping' ? '#3b82f6' : '#7c3aed' }};">
                                    {{ $r['role_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $r['completed'] }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">
                                @if ($r['open_assigned'] === null)
                                    <span class="text-[#9ca3af]">—</span>
                                @else
                                    <span class="{{ $r['open_assigned'] > 0 ? 'font-semibold text-[#d97706]' : '' }}">{{ $r['open_assigned'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">
                                {{ $r['items_logged'] === null ? '—' : $r['items_logged'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} staff</p>
                @if ($rows->hasPages())
                    @php $last = $rows->lastPage(); $cur = $rows->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($rows->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $rows->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
