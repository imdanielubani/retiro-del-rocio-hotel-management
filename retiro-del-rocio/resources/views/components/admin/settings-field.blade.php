@props([
    'label',
    'name',
    'type' => 'text',
    'required' => false,
])

<div>
    <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">
        {{ $label }}@if ($required)<span class="text-[#dc2626]"> *</span>@endif
    </label>
    <input type="{{ $type }}" wire:model="{{ $name }}"
           class="h-12 w-full rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
    @error($name) <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
</div>
