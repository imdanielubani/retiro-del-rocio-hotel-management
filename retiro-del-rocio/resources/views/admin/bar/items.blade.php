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
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[220px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, SKU, brand…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'All', 'in_stock' => 'In Stock', 'low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock'] as $key => $label)
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
            <button type="button" wire:click="openCreate"
                    class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Item
            </button>
        </div>
    </div>

    {{-- ===== Items table ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($items->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No inventory items found</p>
                <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Item</button>
            </div>
        @else
            <table class="w-full min-w-[960px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Category', 'SKU', 'Unit', 'Cost / Sell', 'Stock', 'Status', 'Action'] as $col)
                            <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl', 'text-right' => $col === 'Action'])>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="bi-{{ $item->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5">
                                <p class="text-[13px] font-semibold text-[#1e1e1e]">{{ $item->name }}</p>
                                <p class="text-[11px] text-[#9ca3af]">{{ $item->brand ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->category ?: '—' }}</td>
                            <td class="px-4 py-3.5 font-mono text-[12px] text-[#6b7280]">{{ $item->sku ?: '—' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->unitLabel() }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#374151]">{{ \App\Models\BarInventoryItem::naira($item->cost_price) }} / {{ \App\Models\BarInventoryItem::naira($item->selling_price) }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->current_stock }} <span class="text-[11px] text-[#9ca3af]">(min {{ $item->minimum_stock_level }})</span></td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badge = match ($item->status()) {
                                        'out_of_stock' => ['bg' => '#fee2e2', 'fg' => '#dc2626'],
                                        'low_stock' => ['bg' => '#fef3c7', 'fg' => '#d97706'],
                                        default => ['bg' => '#dcfce7', 'fg' => '#16a34a'],
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $badge['bg'] }}; color: {{ $badge['fg'] }};">{{ $item->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" wire:click="openEdit({{ $item->id }})"
                                            class="rounded-md border border-[#e5e7eb] bg-white px-3 py-1.5 text-[11px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Edit</button>
                                    <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="Remove {{ $item->name }} from the catalog?"
                                            class="rounded-md border border-[#fecaca] bg-white px-3 py-1.5 text-[11px] font-medium text-[#dc2626] transition hover:bg-[#fef2f2]">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

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

    {{-- ===== Add / Edit modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[620px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Bar Inventory</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit — '.$fName : 'Add Item' }}</h2>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Item Name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. Hennessy VS"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Category</label>
                        <input type="text" wire:model="fCategory" placeholder="e.g. Spirits"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">SKU / Barcode</label>
                        <input type="text" wire:model="fSku"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Unit</label>
                        <select wire:model="fUnit" class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @foreach (\App\Models\BarInventoryItem::UNITS as $u)
                                <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Brand</label>
                        <input type="text" wire:model="fBrand"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Supplier</label>
                        <input type="text" wire:model="fSupplier"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Cost Price (₦)</label>
                        <input type="number" min="0" wire:model="fCostPrice"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fCostPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Selling Price (₦)</label>
                        <input type="number" min="0" wire:model="fSellingPrice"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fSellingPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Current Stock</label>
                        <input type="number" min="0" wire:model="fCurrentStock"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fCurrentStock') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Minimum Stock Level</label>
                        <input type="number" min="0" wire:model="fMinimumStockLevel"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fMinimumStockLevel') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        {{ $editingId ? 'Save Changes' : 'Add Item' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
