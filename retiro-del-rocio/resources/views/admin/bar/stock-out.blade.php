<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between rounded-2xl border border-[#e5e7eb] bg-white p-3.5">
        <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $movements->total() }}</span> stock-out entries</p>
        <button type="button" wire:click="openCreate"
                class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Issue Stock
        </button>
    </div>

    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($movements->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No stock issued yet</p>
                <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Issue Stock</button>
            </div>
        @else
            <table class="w-full min-w-[860px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Qty', 'Reason', 'Staff', 'Date'] as $col)
                            <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movements as $m)
                        <tr wire:key="so-{{ $m->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $m->item?->name ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->quantity }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full bg-[#fee2e2] px-2.5 py-1 text-[11px] font-semibold text-[#dc2626]">{{ $m->reasonLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->staff_name ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($m->occurred_at)->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $movements->firstItem() ?? 0 }}–{{ $movements->lastItem() ?? 0 }} of {{ number_format($movements->total()) }} entries</p>
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

    {{-- ===== Issue stock modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[520px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Bar Inventory</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">Issue Stock</h2>
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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Quantity Issued</label>
                        <input type="number" min="1" wire:model="fQuantity"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fQuantity') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Reason</label>
                        <select wire:model="fReason" class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @foreach (\App\Models\BarStockMovement::OUT_REASONS as $r)
                                <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Staff Responsible</label>
                        <input type="text" wire:model="fStaffName"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Date</label>
                        <input type="date" wire:model="fDate"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fDate') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Issue Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
