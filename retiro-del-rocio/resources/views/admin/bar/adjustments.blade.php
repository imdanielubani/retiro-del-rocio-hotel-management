<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between rounded-2xl border border-[#e5e7eb] bg-white p-3.5">
        <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $movements->total() }}</span> adjustments</p>
        <button type="button" wire:click="openCreate"
                class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            New Adjustment
        </button>
    </div>

    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($movements->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No adjustments recorded yet</p>
                <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">New Adjustment</button>
            </div>
        @else
            <table class="w-full min-w-[900px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Direction', 'Qty', 'Reason', 'Notes', 'Date'] as $col)
                            <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $m)
                        <tr wire:key="adj-{{ $m->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $m->item?->name ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                @php $up = $m->type === \App\Models\BarStockMovement::ADJUSTMENT_INCREASE; @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $up ? '#dcfce7' : '#fee2e2' }}; color: {{ $up ? '#16a34a' : '#dc2626' }};">{{ $up ? 'Increase' : 'Decrease' }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->quantity }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->reason ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $m->notes ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($m->occurred_at)->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $movements->firstItem() ?? 0 }}–{{ $movements->lastItem() ?? 0 }} of {{ number_format($movements->total()) }} adjustments</p>
                @if ($movements->hasPages())
                    @php $last = $movements->lastPage(); $cur = $movements->currentPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($movements->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition', 'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $movements->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== New adjustment modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[520px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Bar Inventory</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">New Stock Adjustment</h2>
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
                            <option value="{{ $it->id }}">{{ $it->name }} ({{ $it->current_stock }} on hand)</option>
                        @endforeach
                    </select>
                    @error('fItemId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Direction</label>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('fDirection', 'increase')"
                                @class(['flex-1 rounded-xl border px-3 py-2.5 text-[13px] font-semibold transition', 'border-[#16a34a] bg-[#dcfce7] text-[#16a34a]' => $fDirection === 'increase', 'border-[#e5e7eb] bg-white text-[#6b7280]' => $fDirection !== 'increase'])>Increase Stock</button>
                        <button type="button" wire:click="$set('fDirection', 'decrease')"
                                @class(['flex-1 rounded-xl border px-3 py-2.5 text-[13px] font-semibold transition', 'border-[#dc2626] bg-[#fee2e2] text-[#dc2626]' => $fDirection === 'decrease', 'border-[#e5e7eb] bg-white text-[#6b7280]' => $fDirection !== 'decrease'])>Decrease Stock</button>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Quantity</label>
                    <input type="number" min="1" wire:model="fQuantity"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fQuantity') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Adjustment Reason</label>
                    <input type="text" wire:model="fReason" placeholder="e.g. Stock count correction"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fReason') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Notes</label>
                    <textarea wire:model="fNotes" rows="3"
                              class="rounded-xl border border-[#e5e7eb] bg-white px-3.5 py-2.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                </div>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
