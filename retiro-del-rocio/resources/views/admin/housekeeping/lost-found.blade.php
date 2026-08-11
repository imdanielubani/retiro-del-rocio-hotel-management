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
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search item or room…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'unclaimed' => 'Unclaimed', 'returned' => 'Returned', 'disposed' => 'Disposed'] as $key => $label)
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
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $items->total() }}</span> items</p>
        </div>
    </div>

    {{-- ===== Items table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($items->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No lost & found items found</p>
                <p class="text-[13px] text-[#6b7280]">Items housekeepers log from their tablet will appear here.</p>
            </div>
        @else
            <table class="w-full min-w-[820px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Room', 'Found By', 'Status', 'Claimant', 'Found', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="lf-{{ $item->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5">
                                <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $item->item_description }}</p>
                                @if ($item->notes)
                                    <p class="max-w-[240px] truncate text-[11px] text-[#9ca3af]">{{ $item->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">
                                @if ($item->roomUnit)
                                    Room {{ $item->roomUnit->number }}
                                    @if ($item->roomUnit->room)
                                        <span class="block text-[11px] text-[#9ca3af]">{{ $item->roomUnit->room->name }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->foundBy?->name ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold"
                                      style="background: {{ match ($item->status) { 'returned' => '#dcfce7', 'disposed' => '#f3f4f6', default => '#fef3c7' } }}; color: {{ match ($item->status) { 'returned' => '#16a34a', 'disposed' => '#6b7280', default => '#d97706' } }};">
                                    {{ $item->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->claimant_name ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($item->found_at)->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-right">
                                @include('admin.housekeeping.partials.lost-found-menu', ['item' => $item])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Footer / pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ number_format($items->total()) }} items</p>
                @if ($items->hasPages())
                    @php $last = $items->lastPage(); $cur = $items->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($items->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $items->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Mark Returned modal ===== --}}
    <div x-data="{ show: @entangle('showReturn') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[440px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Housekeeping</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">Mark Returned</h2>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="confirmReturn" class="mt-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Claimant Name (Optional)</label>
                    <input type="text" wire:model="claimantName" placeholder="Who picked it up"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('claimantName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Claimant Contact (Optional)</label>
                    <input type="text" wire:model="claimantContact" placeholder="Phone or room number"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('claimantContact') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#16a34a] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#15803d]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        Confirm Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
