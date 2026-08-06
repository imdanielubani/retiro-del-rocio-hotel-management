<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between rounded-2xl border border-[#e5e7eb] bg-white p-3.5">
        <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $bottles->total() }}</span> bottles tracked</p>
        <button type="button" wire:click="openCreate"
                class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Add Bottle
        </button>
    </div>

    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($bottles->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 22h8M12 11v11M5 3h14l-7 8z"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No bottles tracked yet</p>
                <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Bottle</button>
            </div>
        @else
            <table class="w-full min-w-[860px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Bottle Size', 'State', 'Remaining', 'Status', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bottles as $b)
                        <tr wire:key="bt-{{ $b->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $b->item?->name ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $b->bottle_size }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $b->opened ? 'Opened' : 'Unopened' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $b->remaining_percent }}%</td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badge = match ($b->status()) {
                                        'empty' => ['bg' => '#fee2e2', 'fg' => '#dc2626'],
                                        'opened' => ['bg' => '#fef3c7', 'fg' => '#d97706'],
                                        default => ['bg' => '#dcfce7', 'fg' => '#16a34a'],
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $badge['bg'] }}; color: {{ $badge['fg'] }};">{{ $b->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" wire:click="openEdit({{ $b->id }})"
                                            class="rounded-md border border-[#e5e7eb] bg-white px-3 py-1.5 text-[11px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Edit</button>
                                    <button type="button" wire:click="delete({{ $b->id }})" wire:confirm="Remove this bottle record?"
                                            class="rounded-md border border-[#fecaca] bg-white px-3 py-1.5 text-[11px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $bottles->firstItem() ?? 0 }}–{{ $bottles->lastItem() ?? 0 }} of {{ number_format($bottles->total()) }} bottles</p>
                @if ($bottles->hasPages())
                    @php $last = $bottles->lastPage(); $cur = $bottles->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($bottles->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $bottles->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Add / Edit bottle modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[480px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Bar Inventory</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Bottle Record' : 'Add Bottle Record' }}</h2>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Item</label>
                    <select wire:model="fItemId" class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        <option value="">Select an item…</option>
                        @foreach ($items as $it)
                            <option value="{{ $it->id }}">{{ $it->name }}</option>
                        @endforeach
                    </select>
                    @error('fItemId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Bottle Size</label>
                    <input type="text" wire:model="fBottleSize" placeholder="e.g. 750ml"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fBottleSize') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" wire:model="fOpened" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                    <span class="text-[14px] font-medium text-[#374151]">Bottle has been opened</span>
                </label>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Remaining Quantity (%)</label>
                    <input type="number" min="0" max="100" wire:model="fRemainingPercent"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fRemainingPercent') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save Changes' : 'Add Bottle' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
