@php
    // Simple vehicle glyph chooser: buses get the bus icon, everything else a car.
    $isBus = fn ($v) => str_contains(strtolower($v->type.' '.$v->name), 'bus');
@endphp

<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="relative flex flex-col gap-1.5 rounded-2xl border-2 bg-white px-6 py-5"
                 style="border-color: {{ $stat['accent'] }}">
                <span class="absolute right-5 top-5 flex size-7 items-center justify-center rounded-lg"
                      style="background: {{ $stat['accent'] }}1a; color: {{ $stat['accent'] }}">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v5h2v-2h10v2h2v-5M7 16h.01M17 16h.01"/></svg>
                </span>
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px] font-medium" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
            {{-- Search --}}
            <div class="relative w-full sm:w-[230px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search vehicles…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Status filter pills (inline counts) --}}
            <div class="no-scrollbar flex flex-nowrap items-center gap-1.5 overflow-x-auto">
                @php
                    $tabs = [
                        '' => ['All Vehicles', $counts['all']],
                        'available' => ['Available', $counts['available']],
                        'in_use' => ['In Use', $counts['in_use']],
                        'maintenance' => ['Maintenance', $counts['maintenance']],
                    ];
                @endphp
                @foreach ($tabs as $key => [$label, $count])
                    <button type="button" wire:click="$set('statusFilter', @js($key))"
                            @class([
                                'shrink-0 whitespace-nowrap rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }} ({{ $count }})</button>
                @endforeach
            </div>
        </div>

        {{-- Count + add --}}
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $vehicles->count() }}</span> {{ Str::plural('vehicle', $vehicles->count()) }}</p>
            <button type="button" wire:click="openCreate"
                    class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Vehicle
            </button>
        </div>
    </div>

    {{-- ===== Vehicle list ===== --}}
    @if ($vehicles->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                <svg class="size-6" viewBox="0 0 24 24" fill="currentColor"><path d="M2 19h20v2H2zM4 17h16l-1-6h-3l-2-7-2 .5 1.5 6.5H8l-1.5-3H5l1 3H4z"/></svg>
            </span>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No vehicles found</p>
            <p class="text-[13px] text-[#6b7280]">Add a vehicle to the fleet — it will appear on the website vehicle pickup.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Vehicle</button>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($vehicles as $v)
                @php $cc = $v->statusColor(); @endphp
                <div class="flex flex-col gap-4 overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white sm:flex-row sm:items-center"
                     wire:key="vehicle-{{ $v->id }}">
                    {{-- Accent bar + vehicle image (same artwork shown on the website) --}}
                    <div class="flex shrink-0 items-stretch self-stretch">
                        <span class="w-1.5 shrink-0" style="background: {{ $cc }}"></span>
                        <span class="m-3 flex h-16 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl" style="background: {{ $cc }}1a; color: {{ $cc }}">
                            @if ($v->imageUrl())
                                <img src="{{ $v->imageUrl() }}" alt="{{ $v->name }}" class="h-full w-full object-contain p-1.5">
                            @elseif ($isBus($v))
                                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16M8 17v2M16 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/></svg>
                            @else
                                <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h2M17 17h2v-4M5 17h14"/><circle cx="8" cy="17" r="1.4"/><circle cx="16" cy="17" r="1.4"/></svg>
                            @endif
                        </span>
                    </div>

                    {{-- Details --}}
                    <div class="flex flex-1 flex-col gap-2 px-4 pb-4 sm:py-4 sm:pl-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <p class="text-[16px] font-bold text-[#1e1e1e]">{{ $v->name }}</p>
                            <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                  style="background: {{ $cc }}1a; color: {{ $cc }}">
                                <span class="size-1.5 rounded-full" style="background: {{ $cc }}"></span>{{ $v->statusLabel() }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-[13px] text-[#6b7280]">
                            @if ($v->free_cancellation)
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-4 text-[#16a34a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Free cancellation
                                </span>
                            @endif
                            <span class="flex items-center gap-1.5">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M4 19a5 5 0 0 1 10 0M17 11a3 3 0 0 0 0-6M20 19a5 5 0 0 0-4-4.9" stroke-linecap="round"/></svg>
                                {{ $v->seats }} Seats
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="7" width="12" height="14" rx="2"/><path d="M9 7V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v3" stroke-linecap="round"/></svg>
                                {{ $v->suitcases }} Suitcases
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                                {{ $v->tripsCount() }} trips
                            </span>
                        </div>
                    </div>

                    {{-- Price + actions --}}
                    <div class="flex items-center justify-between gap-3 px-4 pb-4 sm:flex-col sm:items-end sm:py-4 sm:pr-6">
                        <div class="text-right leading-tight">
                            <p class="text-[20px] font-bold text-[#f38c00]">{{ $v->priceLabel() }}</p>
                            <p class="text-[11px] text-[#9ca3af]">per trip</p>
                        </div>
                        <div class="relative flex items-center gap-2"
                             x-data="{ confirming: false }">
                            <button type="button" wire:click="openEdit({{ $v->id }})"
                                    class="flex items-center gap-1.5 rounded-lg border border-[#e5e7eb] bg-white px-3 py-1.5 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                Edit
                            </button>
                            <button type="button" @click="confirming = true"
                                    class="flex size-8 items-center justify-center rounded-lg border border-[#fecaca] bg-[#fef2f2] text-[#dc2626] transition hover:bg-[#fee2e2]">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                            </button>

                            {{-- Delete confirm popover --}}
                            <div x-show="confirming" x-cloak @click.outside="confirming = false"
                                 x-transition.opacity
                                 class="absolute right-0 top-full z-30 mt-2 w-56 rounded-xl border border-[#e5e7eb] bg-white p-3 shadow-lg">
                                <p class="text-[13px] font-semibold text-[#1e1e1e]">Remove {{ $v->name }}?</p>
                                <p class="mt-1 text-[12px] text-[#6b7280]">This vehicle will no longer appear on the website.</p>
                                <div class="mt-3 flex justify-end gap-2">
                                    <button type="button" @click="confirming = false" class="rounded-lg px-3 py-1.5 text-[12px] font-medium text-[#6b7280] hover:bg-[#f9fafb]">Cancel</button>
                                    <button type="button" @click="confirming = false" wire:click="delete({{ $v->id }})" class="rounded-lg bg-[#dc2626] px-3 py-1.5 text-[12px] font-semibold text-white hover:bg-[#b91c1c]">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak
         x-transition.opacity
         class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-black/50 px-4 py-8"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>

        <div class="relative z-10 w-full max-w-[560px] rounded-2xl bg-white p-6 shadow-xl sm:p-7"
             x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Transport</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">
                        {{ $editingId ? 'Edit — '.$fName : 'Add New Vehicle' }}
                    </h2>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex flex-col gap-4"
                  x-data="{
                      name: @entangle('fName'),
                      price: @entangle('fPrice'),
                      seats: @entangle('fSeats'),
                      suitcases: @entangle('fSuitcases'),
                      free: @entangle('fFreeCancellation'),
                      get priceLabel() {
                          const n = parseInt(String(this.price).replace(/[^0-9]/g, '')) || 0;
                          return '₦' + n.toLocaleString();
                      },
                  }">
                {{-- Name + Type --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Vehicle Name</label>
                        <input type="text" wire:model="fName" x-model="name" placeholder="e.g. Executive SUV"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Vehicle Type</label>
                        <select wire:model="fType"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">Select type</option>
                            @foreach ($typeOptions as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Vehicle image --}}
                @php $previewImg = $this->imagePreviewUrl(); @endphp
                <div class="flex flex-col gap-1.5" wire:key="vimg-{{ $editingId ?? 'new' }}">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Vehicle Image</label>
                    <div class="flex items-center gap-4">
                        <span class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#e5e7eb] bg-[#f9fafb]">
                            @if ($previewImg)
                                <img src="{{ $previewImg }}" alt="" class="h-full w-full object-contain p-1.5">
                            @else
                                <svg class="size-7 text-[#cbd5e1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h2M17 17h2v-4M5 17h14"/><circle cx="8" cy="17" r="1.4"/><circle cx="16" cy="17" r="1.4"/></svg>
                            @endif
                        </span>
                        <div class="flex flex-col items-start gap-1.5">
                            <label class="cursor-pointer rounded-xl border border-[#e5e7eb] bg-white px-4 py-2 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                                <span wire:loading.remove wire:target="fImage">{{ $previewImg ? 'Change image' : 'Upload image' }}</span>
                                <span wire:loading wire:target="fImage">Uploading…</span>
                                <input type="file" wire:model="fImage" accept="image/*" class="hidden">
                            </label>
                            <p class="text-[11px] text-[#9ca3af]">PNG or JPG, up to 4MB. A transparent PNG looks best.</p>
                            @error('fImage') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Price --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Price per Trip (₦)</label>
                    <div class="flex h-11 items-center rounded-xl border border-[#e5e7eb] bg-white px-3.5 focus-within:border-[#f38c00] focus-within:ring-2 focus-within:ring-[#f38c00]/15">
                        <span class="mr-2 text-[15px] font-semibold text-[#9ca3af]">₦</span>
                        <input type="number" min="0" wire:model="fPrice" x-model="price" placeholder="0"
                               class="h-full w-full bg-transparent text-[14px] text-[#1e1e1e] focus:outline-none">
                    </div>
                    <span class="text-[11px] font-medium text-[#f38c00]" x-show="(parseInt(String(price).replace(/[^0-9]/g,''))||0) > 0">Formatted: <span x-text="priceLabel"></span></span>
                    @error('fPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                {{-- Seats + Suitcases --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Passenger Seats</label>
                        <input type="number" min="1" wire:model="fSeats" x-model="seats"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fSeats') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Suitcase Capacity</label>
                        <input type="number" min="0" wire:model="fSuitcases" x-model="suitcases"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fSuitcases') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Status + Free cancellation --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Status</label>
                        <select wire:model="fStatus"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="available">Available</option>
                            <option value="in_use">In Use</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2.5 self-end pb-2.5">
                        <input type="checkbox" wire:model="fFreeCancellation" x-model="free"
                               class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[14px] font-medium text-[#374151]">Free Cancellation</span>
                    </label>
                </div>

                {{-- Live preview --}}
                <div class="flex items-center gap-4 rounded-xl px-4 py-3.5"
                     style="background-image: linear-gradient(135deg, #1c2417, #2b3422);">
                    <p class="absolute -mt-9 text-[10px] font-bold uppercase tracking-[1px] text-[#f38c00]">Preview</p>
                    <span class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#f38c00]/20 text-[#f38c00]">
                        @if ($previewImg)
                            <img src="{{ $previewImg }}" alt="" class="h-full w-full object-contain p-1">
                        @else
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h2l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13h2M5 13v4h2M17 17h2v-4M5 17h14"/><circle cx="8" cy="17" r="1.4"/><circle cx="16" cy="17" r="1.4"/></svg>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-bold text-white" x-text="name || 'Vehicle Name'"></p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-[#c7cfc0]">
                            <span><span x-text="seats || 0"></span> Seats</span>
                            <span><span x-text="suitcases || 0"></span> Suitcases</span>
                            <span x-show="free" class="text-[#86efac]">Free cancellation</span>
                        </div>
                    </div>
                    <div class="shrink-0 text-right leading-tight">
                        <p class="text-[15px] font-bold text-white" x-text="priceLabel"></p>
                        <p class="text-[10px] text-[#9ca3af]">per trip</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false"
                            class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit"
                            class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        {{ $editingId ? 'Save Changes' : 'Add Vehicle' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
