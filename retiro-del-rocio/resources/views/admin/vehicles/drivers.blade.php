<div class="flex flex-col gap-4" x-data="{ showFilters: @js($showFilters) }">
    {{-- ===== Stat cards (consistent with the other admin modules) ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Filter bar (mirrors the Bookings / Gym / Spa modules) ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-[13px] -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, phone or vehicle…"
                       class="h-[34px] w-full rounded-[9px] border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-3 text-[12px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Quick status pills --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($statusTabs as $key => [$label, $count])
                    <button type="button" wire:click="$set('statusFilter', @js($key))"
                            @class([
                                'rounded-lg border px-3.5 py-[7px] text-[12px] transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }} ({{ $count }})</button>
                @endforeach
            </div>

            {{-- Filters toggle --}}
            <button type="button" @click="showFilters = !showFilters"
                    :class="showFilters ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                    class="flex items-center gap-2 rounded-lg border px-3.5 py-[7px] text-[12px] transition hover:bg-[#f9fafb]">
                <svg class="size-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
            </button>

            {{-- Summary + clear + add --}}
            <div class="ml-auto flex items-center gap-3">
                <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $filteredCount }}</span> {{ \Illuminate\Support\Str::plural('driver', $filteredCount) }}</p>
                @if ($hasFilters)
                    <button type="button" wire:click="clearAll"
                            class="flex items-center gap-1 rounded-[7px] bg-[#fee2e2] px-2.5 py-[5px] text-[11px] font-semibold text-[#dc2626] transition hover:bg-[#fdd]">
                        <svg class="size-[11px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Clear all
                    </button>
                @endif
                <button type="button" wire:click="openCreate"
                        class="flex h-[34px] shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add Driver
                </button>
            </div>
        </div>

        {{-- Advanced filters (toggled) --}}
        <div x-show="showFilters" x-cloak x-collapse>
            <div class="flex flex-nowrap items-end gap-3 overflow-x-auto border-t border-[#e5e7eb] pt-4">
                <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Sort by</label>
                    <select wire:model.live="sort" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="name">Name (A–Z)</option>
                        <option value="recent">Recently added</option>
                        <option value="trips">Most active trips</option>
                    </select>
                </div>
                <div class="flex min-w-[150px] flex-1 flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Status</label>
                    <select wire:model.live="statusFilter" class="h-9 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="on_trip">On a Trip</option>
                        <option value="off_duty">Off Duty</option>
                    </select>
                </div>
                <div class="flex min-w-[240px] flex-[2] flex-col gap-1.5">
                    <label class="text-[11px] uppercase tracking-wide text-[#6b7280]">Added between</label>
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="from" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                        <span class="text-[#9ca3af]">→</span>
                        <input type="date" wire:model.live="to" class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[13px] text-[#1e1e1e] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Driver list ===== --}}
    @if ($drivers->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>
            </span>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">{{ $hasFilters ? 'No drivers match these filters' : 'No drivers yet' }}</p>
            <p class="text-[13px] text-[#6b7280]">{{ $hasFilters ? 'Try adjusting the search or filters.' : 'Add a driver — reception and admin can then assign them to guest pickups.' }}</p>
            @if ($hasFilters)
                <button type="button" wire:click="clearAll" class="mt-1 rounded-xl border border-[#e5e7eb] px-4 py-2 text-[13px] font-semibold text-[#374151] hover:bg-[#f9fafb]">Clear all filters</button>
            @else
                <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Driver</button>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($drivers as $d)
                @php $cc = $d->isAvailable() ? '#16a34a' : '#6b7280'; @endphp
                <div class="flex flex-col gap-4 rounded-2xl border border-[#e5e7eb] border-l-4 bg-white p-4 sm:flex-row sm:items-center"
                     style="border-left-color: {{ $cc }}" wire:key="driver-{{ $d->id }}">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-xl text-[18px] font-bold" style="background: {{ $cc }}1a; color: {{ $cc }}">
                        {{ \Illuminate\Support\Str::of($d->name)->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') ?: 'D' }}
                    </span>

                    <div class="flex flex-1 flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <p class="text-[16px] font-bold text-[#1e1e1e]">{{ $d->name }}</p>
                            <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                  style="background: {{ $cc }}1a; color: {{ $cc }}">
                                <span class="size-1.5 rounded-full" style="background: {{ $cc }}"></span>{{ $d->statusLabel() }}
                            </span>
                            @if ($d->active_trips_count > 0)
                                <span class="rounded-full bg-[#7c3aed]/10 px-2.5 py-0.5 text-[11px] font-semibold text-[#7c3aed]">{{ $d->active_trips_count }} active {{ Str::plural('trip', $d->active_trips_count) }}</span>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-[#6b7280]">
                            @if ($d->phone)
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2z"/></svg>
                                    {{ $d->phone }}
                                </span>
                            @endif
                            @if ($d->vehicle_details)
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h2M17 17h2v-4M5 17h14"/><circle cx="8" cy="17" r="1.4"/><circle cx="16" cy="17" r="1.4"/></svg>
                                    {{ $d->vehicle_details }}
                                </span>
                            @endif
                            @if ($d->license_no)
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4M15 9h2M15 13h2M7 9h.01" stroke-linecap="round"/></svg>
                                    {{ $d->license_no }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- 3-dot action menu (mirrors the Apartment Bookings action menu) --}}
                    <div x-data="{ open: false }" class="relative flex justify-end">
                        <button type="button" @click="open = !open"
                                :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
                                class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                        </button>

                        <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
                             class="absolute right-0 top-9 z-50 w-[232px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
                            <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
                                <p class="text-[11px] text-[#9ca3af]">{{ $d->statusLabel() }}</p>
                                <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $d->name }}</p>
                            </div>

                            <button type="button" @click="open = false" wire:click="openEdit({{ $d->id }})"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] whitespace-nowrap text-[#374151] transition hover:bg-[#f9fafb]">
                                <svg class="size-4 shrink-0 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                Edit Driver
                            </button>

                            <button type="button" @click="open = false" wire:click="toggleStatus({{ $d->id }})"
                                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium whitespace-nowrap transition {{ $d->isAvailable() ? 'text-[#6b7280] hover:bg-[#f9fafb]' : 'text-[#16a34a] hover:bg-[#f0fdf4]' }}">
                                @if ($d->isAvailable())
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                                    Set Off Duty
                                @else
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                                    Set Available
                                @endif
                            </button>

                            <button type="button" @click="open = false" wire:click="delete({{ $d->id }})" wire:confirm="Remove {{ $d->name }} from the roster?"
                                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] font-medium whitespace-nowrap text-[#dc2626] transition hover:bg-[#fef2f2]">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                Remove Driver
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <div class="relative w-full max-w-[480px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Driver' : 'Add Driver' }}</p>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex flex-col gap-4 px-5 py-5">
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Full name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. Musa Bello"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Phone</label>
                            <input type="text" wire:model="fPhone" placeholder="+234…"
                                   class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fPhone') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Licence No.</label>
                            <input type="text" wire:model="fLicense" placeholder="Optional"
                                   class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Vehicle details</label>
                        <input type="text" wire:model="fVehicle" placeholder="e.g. Toyota Sienna · ABC-123-XY"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Status</label>
                        <select wire:model="fStatus" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="available">Available</option>
                            <option value="off_duty">Off duty</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Notes</label>
                        <textarea wire:model="fNotes" rows="2" placeholder="Optional"
                                  class="w-full rounded-xl border border-[#e5e7eb] px-3.5 py-2.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-[#e5e7eb] px-5 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] hover:bg-[#f9fafb]">Cancel</button>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60">{{ $editingId ? 'Save changes' : 'Add driver' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
