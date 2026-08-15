{{--
    A password/PIN-style input with a show/hide toggle, optionally paired
    with a "Generate" button. Expects:
      $model      — the Livewire property to bind (e.g. "fPassword")
      $label      — field label
      $placeholder
      $generate   — Livewire method to call for a random value, or null to omit the button
      $errorKey   — defaults to $model
--}}
@php $errorKey = $errorKey ?? $model; @endphp
<div class="flex flex-col gap-1.5" x-data="{ show: false }">
    <div class="flex items-center justify-between">
        <label class="text-[12px] font-semibold text-[#374151]">{{ $label }}</label>
        @isset($generate)
            <button type="button" wire:click="{{ $generate }}" class="text-[11px] font-semibold text-[#f38c00] hover:underline">Generate</button>
        @endisset
    </div>
    <div class="relative">
        <input :type="show ? 'text' : 'password'" wire:model="{{ $model }}" placeholder="{{ $placeholder }}"
               class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 pr-11 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
        <button type="button" @click="show = !show" tabindex="-1"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9ca3af] hover:text-[#6b7280]" aria-label="Show/hide">
            <svg x-show="!show" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg x-show="show" x-cloak class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61C3.06 8.9 1 12 1 12s4 7 11 7a9.26 9.26 0 0 0 5.39-1.61M1 1l22 22"/><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/></svg>
        </button>
    </div>
    @error($errorKey) <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
</div>
