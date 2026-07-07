<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-7 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(20px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['subColor'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
            {{-- Search --}}
            <div class="relative w-full sm:w-[220px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search services…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Category dropdown --}}
            <select wire:model.live="categoryFilter"
                    class="h-9 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-2.5 text-[12px] text-[#374151] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                <option value="">All Category</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>

            <div class="hidden h-7 w-px bg-[#e5e7eb] sm:block"></div>

            {{-- Status pills --}}
            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                    <button type="button" wire:click="$set('statusFilter', @js($key))"
                            @class([
                                'rounded-[7px] border px-3 py-1.5 text-[11px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-bold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Count + add --}}
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $services->count() }}</span> services</p>
            <button type="button" wire:click="openCreate"
                    class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Service
            </button>
        </div>
    </div>

    {{-- ===== Service rows ===== --}}
    @if ($services->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M12 2C8 6 8 10 12 14c4-4 4-8 0-12zM5 14c2 2 5 2 7 0M12 14c2 2 5 2 7 0M12 14v8" stroke-linecap="round"/></svg>
            </span>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No services found</p>
            <p class="text-[13px] text-[#6b7280]">Add a spa service — it will appear in the website Book Session popup.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Service</button>
        </div>
    @else
        <div class="flex flex-col gap-3.5">
            @foreach ($services as $s)
                @php $cat = $s->category; @endphp
                <div class="flex overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white" wire:key="spa-{{ $s->id }}">
                    {{-- accent strip --}}
                    <span class="w-1 shrink-0" style="background: {{ $s->is_active ? '#16a34a' : '#d97706' }}"></span>

                    {{-- image --}}
                    <div class="hidden h-auto w-[150px] shrink-0 self-stretch overflow-hidden bg-[#f3f4f6] sm:block">
                        @if ($s->imageUrl())
                            <img src="{{ $s->imageUrl() }}" alt="{{ $s->name }}" class="h-full min-h-[110px] w-full object-cover">
                        @endif
                    </div>

                    {{-- body --}}
                    <div class="flex flex-1 flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-[15px] font-bold tracking-[-0.2px] text-[#1e1e1e]">{{ $s->name }}</p>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $s->is_active ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                                    <span class="size-[5px] rounded-full {{ $s->is_active ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ $s->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if ($cat)
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.5px]" style="background: {{ $cat->color }}1a; color: {{ $cat->color }};">{{ $cat->name }}</span>
                                @endif
                            </div>
                            @if ($s->description)
                                <p class="mt-1.5 line-clamp-2 max-w-[520px] text-[12px] leading-[18px] text-[#6b7280]">{{ $s->description }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-[#6b7280]">
                                @if ($s->duration_minutes)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        {{ $s->duration_minutes }} min
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                                    {{ $bookingCounts[$s->slug] ?? 0 }} bookings
                                </span>
                            </div>
                        </div>

                        {{-- price --}}
                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-[22px] font-extrabold tracking-[-0.5px] text-[#f38c00]">{{ $s->priceLabel() }}</p>
                            <p class="text-[10px] text-[#6b7280]">per guest</p>
                        </div>

                        {{-- action menu --}}
                        <div class="shrink-0 self-start sm:self-center" x-data="{ open: false, confirm: false, pos: {} }" @keydown.escape.window="open = false">
                            <button type="button" x-ref="trigger"
                                    @click="open = !open; confirm = false; if (open) $nextTick(() => { const r = $refs.trigger.getBoundingClientRect(); pos = { top: (r.bottom + 6) + 'px', left: (r.right - 192) + 'px' } })"
                                    class="flex size-[30px] items-center justify-center rounded-[7px] border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb]">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity
                                 :style="`top:${pos.top}; left:${pos.left}`"
                                 class="fixed z-[100] w-48 overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
                                <template x-if="!confirm">
                                    <div>
                                        <button type="button" wire:click="openEdit({{ $s->id }})" @click="open = false"
                                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                                            <svg class="size-4 text-[#6b7280]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                            Edit Service
                                        </button>
                                        <button type="button" wire:click="toggleActive({{ $s->id }})" @click="open = false"
                                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#374151] transition hover:bg-[#f9fafb]">
                                            @if ($s->is_active)
                                                <svg class="size-4 text-[#d97706]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 2v10"/></svg>
                                                Deactivate
                                            @else
                                                <svg class="size-4 text-[#16a34a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                                Activate
                                            @endif
                                        </button>
                                        <button type="button" @click="confirm = true"
                                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-[13px] text-[#dc2626] transition hover:bg-[#fef2f2]">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                            Delete Service
                                        </button>
                                    </div>
                                </template>
                                <template x-if="confirm">
                                    <div class="px-3 py-2">
                                        <p class="text-[12px] font-semibold text-[#1e1e1e]">Delete {{ $s->name }}?</p>
                                        <div class="mt-2 flex justify-end gap-2">
                                            <button type="button" @click="confirm = false" class="rounded-lg px-2.5 py-1 text-[12px] text-[#6b7280] hover:bg-[#f9fafb]">Cancel</button>
                                            <button type="button" wire:click="delete({{ $s->id }})" @click="open = false" class="rounded-lg bg-[#dc2626] px-2.5 py-1 text-[12px] font-semibold text-white hover:bg-[#b91c1c]">Delete</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[600px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Spa &amp; Wellness</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit — '.$fName : 'Add New Service' }}</h2>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Service Name</label>
                    <input type="text" wire:model="fName" placeholder="e.g. Hot Stone Massage"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                {{-- Category --}}
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Category</label>
                        <button type="button" wire:click="$toggle('fNewCat')"
                                class="text-[11px] font-semibold text-[#f38c00] hover:underline">{{ $fNewCat ? 'Choose existing' : '+ New category' }}</button>
                    </div>

                    @if (! $fNewCat)
                        <select wire:model="fCategoryId"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">Select a category…</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('fCategoryId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    @else
                        <div class="flex flex-col gap-3 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] p-3">
                            <input type="text" wire:model="fNewCatName" placeholder="New category name (e.g. Body Scrub)"
                                   class="h-10 rounded-lg border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fNewCatName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                            <div>
                                <p class="mb-1.5 text-[11px] font-medium text-[#6b7280]">Category colour</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($palette as $color)
                                        <button type="button" wire:click="$set('fNewCatColor', @js($color))"
                                                class="flex size-7 items-center justify-center rounded-full ring-offset-2 transition {{ $fNewCatColor === $color ? 'ring-2 ring-[#1e1e1e]' : '' }}"
                                                style="background: {{ $color }}">
                                            @if ($fNewCatColor === $color)
                                                <svg class="size-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] text-[#6b7280]">Preview:</span>
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.5px]" style="background: {{ $fNewCatColor }}1a; color: {{ $fNewCatColor }};">{{ $fNewCatName ?: 'Category' }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Image --}}
                @php $previewImg = $this->imagePreviewUrl(); @endphp
                <div class="flex flex-col gap-1.5" wire:key="simg-{{ $editingId ?? 'new' }}">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Service Image</label>
                    <div class="flex items-center gap-4">
                        <span class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#e5e7eb] bg-[#f9fafb]">
                            @if ($previewImg)
                                <img src="{{ $previewImg }}" alt="" class="h-full w-full object-cover">
                            @else
                                <svg class="size-7 text-[#cbd5e1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                            @endif
                        </span>
                        <div class="flex flex-col items-start gap-1.5" x-data="cmsImageUpload('fImage')">
                            <label class="cursor-pointer rounded-xl border border-[#e5e7eb] bg-white px-4 py-2 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                                <span x-show="!uploading">{{ $previewImg ? 'Change image' : 'Upload image' }}</span>
                                <span x-show="uploading" x-cloak>Uploading… <span x-text="progress + '%'"></span></span>
                                <input type="file" accept="image/*" class="hidden" @change="handle($event)">
                            </label>
                            <p class="text-[11px] text-[#9ca3af]">PNG or JPG, up to 5MB.</p>
                            @error('fImage') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Price + duration --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Price per Guest (₦)</label>
                        <div class="flex h-11 items-center rounded-xl border border-[#e5e7eb] bg-white px-3.5 focus-within:border-[#f38c00] focus-within:ring-2 focus-within:ring-[#f38c00]/15">
                            <span class="mr-2 text-[15px] font-semibold text-[#9ca3af]">₦</span>
                            <input type="number" min="0" wire:model="fPrice" placeholder="0" class="h-full w-full bg-transparent text-[14px] text-[#1e1e1e] focus:outline-none">
                        </div>
                        @error('fPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Duration (min)</label>
                        <input type="number" min="0" max="600" wire:model="fDuration" placeholder="e.g. 60"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fDuration') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Description --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Description</label>
                    <textarea wire:model="fDescription" rows="3" placeholder="Short description shown on the service card"
                              class="rounded-xl border border-[#e5e7eb] bg-white p-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                    @error('fDescription') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-2.5">
                    <input type="checkbox" wire:model="fActive" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                    <span class="text-[14px] font-medium text-[#374151]">Active (show on the website)</span>
                </label>

                <div class="mt-1 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        {{ $editingId ? 'Save Changes' : 'Add Service' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
