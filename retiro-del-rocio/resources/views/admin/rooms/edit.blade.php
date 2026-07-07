@php
    $resolve = function ($p) {
        if (! $p) return null;
        if (str_starts_with($p, 'images/')) return str_replace(' ', '%20', asset($p));
        return \Illuminate\Support\Facades\Storage::disk('public')->url($p);
    };
@endphp

<form wire:submit="save" class="flex flex-col gap-4">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.rooms.index') }}" wire:navigate class="flex items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to rooms
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.rooms.index') }}" wire:navigate class="rounded-xl border border-[#e5e7eb] bg-white px-4 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</a>
            <button type="submit" wire:loading.attr="disabled"
                    class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60">
                <svg wire:loading class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.6" stroke-linecap="round"/></svg>
                {{ $roomId ? 'Save changes' : 'Create room' }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_360px]">
        {{-- ===== Main column ===== --}}
        <div class="flex flex-col gap-4">
            {{-- Details --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Room details</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Name</label>
                        <input type="text" wire:model="name" placeholder="e.g. Pandora's Suite"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('name') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div x-data="{
                            type: @entangle('type'),
                            options: @js($categoryOptions),
                            custom: false,
                            init() { this.custom = !!(this.type && !this.options.includes(this.type)); },
                         }">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Type / Category</label>

                        {{-- Select an existing category --}}
                        <div x-show="!custom" class="relative">
                            <select x-model="type"
                                    @change="if ($event.target.value === '__new__') { custom = true; type = ''; $nextTick(() => $refs.customInput && $refs.customInput.focus()); }"
                                    class="h-11 w-full appearance-none rounded-xl border border-[#e5e7eb] bg-white px-3.5 pr-10 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="">Select category…</option>
                                <template x-for="opt in options" :key="opt">
                                    <option :value="opt" x-text="opt"></option>
                                </template>
                                <option value="__new__">➕ Add new category…</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </div>

                        {{-- Add a brand-new category --}}
                        <div x-show="custom" x-cloak class="flex items-center gap-2">
                            <input type="text" x-ref="customInput" x-model="type" placeholder="e.g. Rocio Loft Residence"
                                   class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <button type="button" @click="custom = false; type = ''"
                                    class="flex h-11 shrink-0 items-center gap-1 rounded-xl border border-[#e5e7eb] px-3 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]" title="Choose from list">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                                List
                            </button>
                        </div>
                        @error('type') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Price / night (₦)</label>
                        <input type="number" min="0" wire:model="price"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('price') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div><label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Beds</label>
                        <input type="number" min="0" wire:model="beds" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></div>
                    <div><label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Guests</label>
                        <input type="number" min="0" wire:model="guests" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></div>
                    <div><label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Size (sq ft)</label>
                        <input type="number" min="0" wire:model="sqft" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></div>
                    <div><label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Bathrooms</label>
                        <input type="number" min="0" wire:model="bathrooms" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Short description</label>
                        <input type="text" wire:model="short_description" placeholder="One-line summary shown on cards"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Full description</label>
                        <textarea wire:model="description" rows="5"
                                  class="w-full rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Cancellation policy</label>
                        <textarea wire:model="cancellation_policy" rows="4" placeholder="Describe the cancellation / refund terms shown on the room page…"
                                  class="w-full rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        @error('cancellation_policy') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Amenities --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Amenities</h2>
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($amenityOptions as $icon => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e5e7eb] px-3.5 py-2.5 transition hover:bg-[#f9fafb] has-[:checked]:border-[#f38c00] has-[:checked]:bg-[#fff7ec]">
                            <input type="checkbox" wire:model="amenities" value="{{ $icon }}" class="size-4 accent-[#f38c00]">
                            <span class="text-[14px] text-[#374151]">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Additional --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Additional</h2>
                <p class="mt-1 text-[12px] text-[#6b7280]">Extra services shown under "Additional" on the room page.</p>
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($additionalOptions as $icon => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#e5e7eb] px-3.5 py-2.5 transition hover:bg-[#f9fafb] has-[:checked]:border-[#f38c00] has-[:checked]:bg-[#fff7ec]">
                            <input type="checkbox" wire:model="additional" value="{{ $icon }}" class="size-4 accent-[#f38c00]">
                            <span class="text-[14px] text-[#374151]">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Gallery --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Gallery images</h2>
                <p class="mt-1 text-[12px] text-[#6b7280]">Shown on the room detail gallery. PNG/JPG up to 5MB each.</p>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($existingGallery as $i => $path)
                        <div wire:key="g-exist-{{ $i }}" class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-[#e5e7eb]">
                            <img src="{{ $resolve($path) }}" alt="" class="h-full w-full object-cover">
                            <button type="button" wire:click="removeExistingGallery({{ $i }})"
                                    class="absolute right-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100" title="Remove">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                    @foreach ($newGallery as $i => $file)
                        <div wire:key="g-new-{{ $i }}" class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-[#f38c00]">
                            <img src="{{ $file->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                            <span class="absolute left-1.5 top-1.5 rounded-full bg-[#f38c00] px-2 py-0.5 text-[10px] font-semibold text-white">New</span>
                            <button type="button" wire:click="removeNewGallery({{ $i }})"
                                    class="absolute right-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100" title="Remove">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach

                    {{-- Add tile --}}
                    <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-[#d6d9d2] text-[#6b7280] transition hover:border-[#f38c00] hover:text-[#f38c00]">
                        <svg wire:loading.remove wire:target="newGallery" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        <svg wire:loading wire:target="newGallery" class="size-6 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.6" stroke-linecap="round"/></svg>
                        <span class="text-[12px] font-medium">Add images</span>
                        <input type="file" wire:model="newGallery" multiple accept="image/*" class="hidden">
                    </label>
                </div>
                @error('newGallery.*') <span class="mt-2 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- ===== Sidebar ===== --}}
        <div class="flex flex-col gap-4">
            {{-- Status --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Visibility</h2>
                <label class="mt-3 flex cursor-pointer items-center justify-between gap-3">
                    <span class="text-[14px] text-[#374151]">Published on website</span>
                    <button type="button" wire:click="$toggle('is_published')"
                            class="relative h-6 w-11 shrink-0 rounded-full transition {{ $is_published ? 'bg-[#16a34a]' : 'bg-[#d1d5db]' }}">
                        <span class="absolute top-0.5 size-5 rounded-full bg-white transition-all {{ $is_published ? 'left-[22px]' : 'left-0.5' }}"></span>
                    </button>
                </label>
                <p class="mt-2 text-[12px] text-[#6b7280]">{{ $is_published ? 'Visible to guests on the public site.' : 'Hidden from the public site (draft).' }}</p>
            </div>

            {{-- Featured image --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Featured image</h2>
                <p class="mt-1 text-[12px] text-[#6b7280]">Main image on cards and listings.</p>

                @php $featuredPreview = $featured ? $featured->temporaryUrl() : $resolve($existingFeatured); @endphp

                <div class="mt-4">
                    @if ($featuredPreview)
                        <div class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-[#e5e7eb]">
                            <img src="{{ $featuredPreview }}" alt="" class="h-full w-full object-cover">
                            <button type="button" wire:click="removeExistingFeatured" @if($featured) onclick="return false" @endif
                                    class="absolute right-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100" title="Remove">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <label class="mt-2 block cursor-pointer text-center text-[13px] font-medium text-[#f38c00] hover:underline">
                            Replace image
                            <input type="file" wire:model="featured" accept="image/*" class="hidden">
                        </label>
                    @else
                        <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-[#d6d9d2] text-[#6b7280] transition hover:border-[#f38c00] hover:text-[#f38c00]">
                            <svg wire:loading.remove wire:target="featured" class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                            <svg wire:loading wire:target="featured" class="size-7 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.6" stroke-linecap="round"/></svg>
                            <span class="text-[13px] font-medium">Upload image</span>
                            <span class="text-[11px]">PNG/JPG up to 5MB</span>
                            <input type="file" wire:model="featured" accept="image/*" class="hidden">
                        </label>
                    @endif
                    @error('featured') <span class="mt-2 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Room Numbers (admin-only units) ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-[15px] font-bold text-[#1e1e1e]">Room Numbers</h2>
                <p class="text-[12px] text-[#6b7280]">Physical rooms assigned to guests at check-in. Admin only — never shown on the website.</p>
            </div>
            @if ($roomId)
                <div class="flex items-center gap-3">
                    <span class="text-[12px] text-[#6b7280]">
                        {{ $units->count() }} {{ \Illuminate\Support\Str::plural('room', $units->count()) }} ·
                        <span class="font-semibold text-[#16a34a]">{{ $units->where('status', 'available')->count() }} available</span> ·
                        <span class="font-semibold text-[#dc2626]">{{ $units->where('status', 'occupied')->count() }} occupied</span>
                    </span>
                    <a href="{{ route('admin.rooms.calendar', $roomId) }}" wire:navigate
                       class="flex items-center gap-1.5 rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>
                        View calendar
                    </a>
                </div>
            @endif
        </div>

        @if (! $roomId)
            <div class="mt-4 rounded-xl border border-dashed border-[#d6d9d2] bg-[#f9fafb] px-4 py-6 text-center text-[13px] text-[#6b7280]">
                Save the room first, then come back to add its room numbers.
            </div>
        @else
            {{-- Add controls --}}
            <div class="mt-4 flex flex-col gap-3 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] p-4 sm:flex-row sm:items-end sm:gap-4">
                <div class="flex flex-1 flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1.5 block text-[12px] font-medium text-[#374151]">Starting number</label>
                        <input type="text" wire:model="unitStart" placeholder="100"
                               class="h-10 w-[120px] rounded-lg border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[12px] font-medium text-[#374151]">Quantity</label>
                        <input type="number" min="1" max="200" wire:model="unitQty"
                               class="h-10 w-[100px] rounded-lg border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <button type="button" wire:click="addUnitRange"
                            class="flex h-10 items-center gap-1.5 rounded-lg bg-[#1e2318] px-4 text-[13px] font-semibold text-white transition hover:bg-[#2b3326]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add range
                    </button>
                    <span class="pb-2.5 text-[11px] text-[#9ca3af]">e.g. start 100, qty 11 → 100–110</span>
                </div>
            </div>
            @error('unitStart') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror

            {{-- Single add --}}
            <div class="mt-3 flex items-end gap-2">
                <div>
                    <label class="mb-1.5 block text-[12px] font-medium text-[#374151]">Add a single number</label>
                    <input type="text" wire:model="unitSingle" placeholder="e.g. 100 or A1"
                           class="h-10 w-[160px] rounded-lg border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                </div>
                <button type="button" wire:click="addUnit"
                        class="flex h-10 items-center gap-1.5 rounded-lg border border-[#e5e7eb] px-4 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Add</button>
            </div>

            {{-- Units list --}}
            @if ($units->isEmpty())
                <p class="mt-4 text-[13px] text-[#6b7280]">No room numbers yet. Add a range above to get started.</p>
            @else
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($units as $unit)
                        <span wire:key="unit-{{ $unit->id }}"
                              class="flex items-center gap-2 rounded-lg border border-[#e5e7eb] bg-white py-1.5 pl-3 pr-1.5 text-[13px]">
                            <span class="font-semibold text-[#1e1e1e]">{{ $unit->number }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $unit->statusBadge() }}">{{ $unit->statusLabel() }}</span>
                            <button type="button" wire:click="removeUnit({{ $unit->id }})" title="Remove"
                                    class="flex size-5 items-center justify-center rounded text-[#9ca3af] transition hover:bg-[#fef2f2] hover:text-[#dc2626]">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</form>
