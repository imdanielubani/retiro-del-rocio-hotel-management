<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search drinks…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'All', 'active' => 'Available', 'inactive' => 'Hidden'] as $key => $label)
                    <button type="button" wire:click="setStatus(@js($key))"
                            @class([
                                'rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="$set('category', '')" @class(['rounded-lg border px-3 py-1.5 text-[12px] font-medium transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $category === '', 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $category !== ''])>All Categories</button>
                @foreach ($categories as $c)
                    <button type="button" wire:click="setCategory('{{ $c->slug }}')" @class(['rounded-lg border px-3 py-1.5 text-[12px] font-medium transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $category === $c->slug, 'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $category !== $c->slug])>{{ $c->name }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $items->count() }}</span> {{ \Illuminate\Support\Str::plural('drink', $items->count()) }}</p>
            <button type="button" wire:click="openCategoryManager" class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-[#e5e7eb] bg-white px-4 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
                Categories
            </button>
            <button type="button" wire:click="openCreate" class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Drink
            </button>
        </div>
    </div>

    {{-- ===== Menu grid ===== --}}
    @if ($items->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No drinks yet</p>
            <p class="text-[13px] text-[#6b7280]">Add a drink — it will appear on the guest tablet's Place Order menu.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Drink</button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($items as $item)
                <div wire:key="drink-{{ $item->id }}" class="relative flex flex-col gap-2.5 rounded-2xl border border-[#e5e7eb] bg-white p-4 text-center">
                    <div class="absolute right-3 top-3">@include('admin.bar-lounge.partials.menu-item-menu', ['item' => $item])</div>
                    <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-xl bg-[#f9fafb]">
                        @if ($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                        @else
                            <svg class="size-8 text-[#d6d9d2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 22h8M12 11v11M5 3h14l-7 8z"/></svg>
                        @endif
                    </div>
                    <span class="mx-auto rounded-full bg-[#f9fafb] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#9ca3af]">{{ $item->categoryLabel() }}</span>
                    <p class="truncate text-[14px] font-semibold text-[#1e1e1e]">{{ $item->name }}</p>
                    <p class="text-[13px] font-bold text-[#f38c00]">{{ $item->priceLabel() }}</p>
                    <span class="mx-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $item->is_active ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                        <span class="size-[5px] rounded-full {{ $item->is_active ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ $item->is_active ? 'On Menu' : 'Hidden' }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 my-auto w-full max-w-[460px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Drink' : 'Add Drink' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="flex max-h-[70vh] flex-col gap-4 overflow-y-auto px-6 py-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Drink name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. Fresh Chapman" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Category</label>
                        <select wire:model="fCategory" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @foreach ($categories as $c)<option value="{{ $c->slug }}">{{ $c->name }}</option>@endforeach
                        </select>
                        @error('fCategory') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Price (₦)</label>
                            <input type="number" min="0" wire:model="fPrice" placeholder="3500" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Prep time (mins)</label>
                            <input type="number" min="0" wire:model="fPrepMinutes" placeholder="5" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fPrepMinutes') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Description</label>
                        <textarea wire:model="fDescription" rows="2" placeholder="Short description shown to guests" class="rounded-xl border border-[#e5e7eb] px-3.5 py-2.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        @error('fDescription') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Image</label>
                        <div class="flex items-center gap-3">
                            <div class="flex h-[72px] w-[72px] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f9fafb]">
                                @if ($fImage)
                                    <img src="{{ $fImage->temporaryUrl() }}" class="h-full w-full object-cover">
                                @elseif ($fImagePath)
                                    <img src="{{ \App\Models\MenuItem::find($editingId)?->imageUrl() }}" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-[#d6d9d2] bg-[#f9fafb] px-3 py-3 text-center text-[12px] text-[#6b7280] transition hover:border-[#f38c00]">
                                <input type="file" wire:model="fImage" accept="image/*" class="hidden">
                                <span wire:loading.remove wire:target="fImage">{{ $fImage || $fImagePath ? 'Change image' : 'Upload image' }}</span>
                                <span wire:loading wire:target="fImage">Uploading…</span>
                            </label>
                        </div>
                        @error('fImage') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                        <input type="checkbox" wire:model="fActive" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[13px] text-[#374151]">On the menu (shown on Place Order)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                        <input type="checkbox" wire:model="fAlcoholic" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[13px] text-[#374151]">Contains alcohol (requires age verification on the Bar Tablet)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Add drink' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @include('admin.bar-lounge.partials.category-manager')
</div>
