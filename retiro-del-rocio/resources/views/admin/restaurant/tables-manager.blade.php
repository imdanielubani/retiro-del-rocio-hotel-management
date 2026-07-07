<div class="flex flex-col gap-4">
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

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="relative w-full sm:w-[260px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search {{ strtolower($plural) }}…"
                   class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
        <div class="flex items-center gap-2">
            @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $k => $l)
                <button type="button" wire:click="setStatus('{{ $k }}')" @class(['rounded-lg border px-3.5 py-[7px] text-[12px] transition', 'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $k, 'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $k])>{{ $l }}</button>
            @endforeach
        </div>
        <button type="button" wire:click="openCreate" class="ml-auto flex h-[34px] items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Add {{ $singular }}
        </button>
    </div>

    {{-- Cards --}}
    @if ($items->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-[#e5e7eb] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No {{ strtolower($plural) }} yet</p>
            <p class="text-[13px] text-[#6b7280]">Add one so guests can reserve it on the restaurant page.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $t)
                <div wire:key="rt-{{ $t->id }}" class="flex flex-col gap-4 rounded-2xl border border-[#e5e7eb] bg-white p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-12 items-center justify-center rounded-xl bg-[#fff3e0] text-[#f38c00]">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 4h4M10 20h4M4 10v4M20 10v4"/></svg>
                            </span>
                            <div>
                                <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $t->name }}</p>
                                <p class="text-[12px] capitalize text-[#9ca3af]">{{ $t->shape }} · {{ $t->capacityLabel() }}</p>
                            </div>
                        </div>
                        @include('admin.restaurant.partials.table-menu', ['t' => $t])
                    </div>
                    @if ($t->description)
                        <p class="text-[13px] leading-relaxed text-[#6b7280]">{{ $t->description }}</p>
                    @endif
                    <div class="mt-auto flex items-center justify-between border-t border-[#f1f1ee] pt-3">
                        <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold', 'bg-[#dcfce7] text-[#16a34a]' => $t->is_active, 'bg-[#f3f4f6] text-[#6b7280]' => ! $t->is_active])>
                            <span @class(['size-1.5 rounded-full', 'bg-[#16a34a]' => $t->is_active, 'bg-[#9ca3af]' => ! $t->is_active])></span>
                            {{ $t->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="text-[12px] font-medium text-[#374151]">{{ $t->capacity }} seats</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add / Edit modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 w-full max-w-[480px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit' : 'Add' }} {{ $singular }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="flex flex-col gap-4 px-6 py-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. 4-Seater Table" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Capacity (seats)</label>
                            <input type="number" min="1" max="50" wire:model="fCapacity" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fCapacity') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Shape</label>
                            <select wire:model="fShape" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="round">Round</option>
                                <option value="square">Square</option>
                                <option value="rectangle">Rectangle</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Description <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                        <input type="text" wire:model="fDescription" placeholder="Short note shown internally" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                        <input type="checkbox" wire:model="fActive" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[13px] text-[#374151]">Active — guests can reserve it</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save changes' : 'Add '.$singular }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
