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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search snacks…"
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
        </div>
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $snacks->count() }}</span> {{ \Illuminate\Support\Str::plural('snack', $snacks->count()) }}</p>
            <button type="button" wire:click="openCreate" class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Snack
            </button>
        </div>
    </div>

    {{-- ===== Snacks grid ===== --}}
    @if ($snacks->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No snacks yet</p>
            <p class="text-[13px] text-[#6b7280]">Add a snack — it will appear on the movie booking page.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Snack</button>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($snacks as $s)
                <div wire:key="snack-{{ $s->id }}" class="relative flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-4 text-center">
                    <div class="absolute right-3 top-3">@include('admin.cinema.partials.snack-menu', ['s' => $s])</div>
                    <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-xl bg-[#f9fafb]">
                        <img src="{{ $s->imageUrl() }}" alt="{{ $s->name }}" class="h-full w-full object-contain p-2">
                    </div>
                    <p class="truncate text-[14px] font-semibold text-[#1e1e1e]">{{ $s->name }}</p>
                    <p class="text-[13px] font-bold text-[#f38c00]">{{ $s->priceLabel() }}</p>
                    <span class="mx-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $s->is_active ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                        <span class="size-[5px] rounded-full {{ $s->is_active ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ $s->is_active ? 'Available' : 'Hidden' }}
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
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Snack' : 'Add Snack' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="flex flex-col gap-4 px-6 py-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Snack name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. Popcorn" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Price (₦)</label>
                        <input type="number" min="0" wire:model="fPrice" placeholder="2500" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Image</label>
                        <div class="flex items-center gap-3">
                            <div class="flex h-[72px] w-[72px] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#f9fafb]">
                                @if ($fImage)
                                    <img src="{{ $fImage->temporaryUrl() }}" class="h-full w-full object-contain p-1.5">
                                @elseif ($fImagePath)
                                    <img src="{{ \App\Models\SiteContent::imageUrl($fImagePath) }}" class="h-full w-full object-contain p-1.5">
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
                        <span class="text-[13px] text-[#374151]">Available (shown on the booking page)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Add snack' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
