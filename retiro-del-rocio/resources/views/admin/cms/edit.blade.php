<div class="flex flex-col gap-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.cms.index') }}" wire:navigate
           class="flex w-fit items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            All pages
        </a>
        <button type="button" wire:click="save"
                class="flex h-11 items-center justify-center gap-2 rounded-xl bg-[#f38c00] px-6 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
            Save changes
        </button>
    </div>

    {{-- Fields card --}}
    <div class="flex flex-col gap-6 rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-7">
        <div>
            <h2 class="text-[18px] font-bold text-[#1e1e1e]">{{ $pageLabel }}</h2>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                @foreach ($pageChips as $chip)
                    <span class="rounded-md bg-[#f3f4f6] px-2 py-0.5 text-[11px] font-medium text-[#6b7280]">{{ $chip }}</span>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($fields as $field)
                @php $name = $field['name']; $type = $field['type']; @endphp

                {{-- Repeater (full width) --}}
                @if ($type === 'repeater')
                    <div class="lg:col-span-2">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-[13px] font-semibold text-[#374151]">{{ $field['label'] }}</label>
                            <button type="button" wire:click="addRow('{{ $name }}')"
                                    class="flex items-center gap-1.5 rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                Add
                            </button>
                        </div>
                        <div class="flex flex-col gap-2.5">
                            @forelse ($repeaters[$name] ?? [] as $i => $row)
                                <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] bg-[#fafaf9] p-3 sm:flex-row sm:items-start" wire:key="{{ $name }}-{{ $i }}">
                                    @foreach ($field['item'] as $col => $colLabel)
                                        <div class="flex-1">
                                            <label class="mb-1 block text-[11px] font-medium text-[#9ca3af]">{{ $colLabel }}</label>
                                            @if (in_array($col, $field['image_cols'] ?? [], true))
                                                @php
                                                    $rUpload = $repeaterUploads[$name][$i][$col] ?? null;
                                                    $rPreview = $rUpload ? $rUpload->temporaryUrl() : \App\Models\SiteContent::imageUrl($row[$col] ?? null);
                                                @endphp
                                                <div class="flex items-center gap-3" x-data="cmsImageUpload('repeaterUploads.{{ $name }}.{{ $i }}.{{ $col }}')">
                                                    <span class="flex h-10 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e5e7eb] bg-[#f9fafb]">
                                                        @if ($rPreview)
                                                            <img src="{{ $rPreview }}" alt="" class="h-full w-full object-contain p-1">
                                                        @else
                                                            <svg class="size-4 text-[#cbd5e1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                                                        @endif
                                                    </span>
                                                    <label class="cursor-pointer rounded-lg border border-[#e5e7eb] bg-white px-3 py-2 text-[12px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                                                        <span x-show="!uploading">{{ $rPreview ? 'Change' : 'Upload' }}</span>
                                                        <span x-show="uploading" x-cloak>… <span x-text="progress + '%'"></span></span>
                                                        <input type="file" accept="image/*" class="hidden" @change="handle($event)">
                                                    </label>
                                                </div>
                                                @error('repeaterUploads.'.$name.'.'.$i.'.'.$col) <span class="mt-1 block text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                                            @elseif (in_array($col, ['a', 'text'], true))
                                                <textarea wire:model="repeaters.{{ $name }}.{{ $i }}.{{ $col }}" rows="2"
                                                          class="w-full rounded-lg border border-[#e5e7eb] p-2.5 text-[13px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                                            @else
                                                <input type="text" wire:model="repeaters.{{ $name }}.{{ $i }}.{{ $col }}"
                                                       class="h-10 w-full rounded-lg border border-[#e5e7eb] px-3 text-[13px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                            @endif
                                        </div>
                                    @endforeach
                                    <button type="button" wire:click="removeRow('{{ $name }}', {{ $i }})"
                                            class="flex size-9 shrink-0 items-center justify-center self-end rounded-lg border border-[#fecaca] bg-[#fef2f2] text-[#dc2626] transition hover:bg-[#fee2e2] sm:mt-[18px]">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                    </button>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-[#e5e7eb] px-4 py-6 text-center text-[13px] text-[#9ca3af]">No items yet — click “Add”.</p>
                            @endforelse
                        </div>
                    </div>

                {{-- Image --}}
                @elseif ($type === 'image')
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">{{ $field['label'] }}</label>
                        @php
                            $preview = ! empty($uploads[$name])
                                ? $uploads[$name]->temporaryUrl()
                                : \App\Models\SiteContent::imageUrl($values[$name] ?? null);
                        @endphp
                        <div class="flex items-center gap-4">
                            <span class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#e5e7eb] bg-[#f9fafb]">
                                @if ($preview)
                                    <img src="{{ $preview }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <svg class="size-6 text-[#cbd5e1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                                @endif
                            </span>
                            <div class="flex flex-col items-start gap-1.5" x-data="cmsImageUpload('uploads.{{ $name }}')">
                                <label class="cursor-pointer rounded-xl border border-[#e5e7eb] bg-white px-4 py-2 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                                    <span x-show="!uploading">{{ $preview ? 'Change image' : 'Upload image' }}</span>
                                    <span x-show="uploading" x-cloak>Uploading… <span x-text="progress + '%'"></span></span>
                                    <input type="file" accept="image/*" class="hidden" @change="handle($event)">
                                </label>
                                @if ($preview)
                                    <button type="button" wire:click="removeImage('{{ $name }}')" class="text-[12px] text-[#dc2626] hover:underline">Remove</button>
                                @endif
                            </div>
                        </div>
                        @error('uploads.'.$name) <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>

                {{-- Textarea --}}
                @elseif ($type === 'textarea')
                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">{{ $field['label'] }}</label>
                        <textarea wire:model="values.{{ $name }}" rows="3"
                                  class="w-full rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                    </div>

                {{-- Text --}}
                @else
                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">{{ $field['label'] }}</label>
                        <input type="text" wire:model="values.{{ $name }}"
                               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                @endif
            @endforeach
        </div>

        <div class="flex justify-end border-t border-[#f1f1ee] pt-5">
            <button type="button" wire:click="save"
                    class="flex h-11 items-center justify-center gap-2 rounded-xl bg-[#f38c00] px-6 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                Save changes
            </button>
        </div>
    </div>
</div>
