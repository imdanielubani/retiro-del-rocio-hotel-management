<div class="flex flex-col gap-4">
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-6">
        @if (! $configured)
            <div class="mb-4 rounded-lg bg-[#fef3c7] px-4 py-3 text-[13px] text-[#92400e]">
                Tuya is not configured yet. Set <code>TUYA_CLIENT_ID</code> / <code>TUYA_CLIENT_SECRET</code>
                (and, once the project mode is confirmed against the live Tuya console,
                <code>TUYA_DISCOVERY_ENDPOINT</code>) in your .env file before syncing.
            </div>
        @endif

        <p class="text-[15px] font-bold text-[#1e1e1e]">Sync Devices from Tuya</p>
        <p class="mt-1 text-[13px] text-[#6b7280]">
            Pulls the device list from the Tuya project and upserts each one into the Smart Devices
            registry, keyed on the Tuya device id. Newly discovered devices are left unassigned —
            they never bind to a room automatically.
        </p>

        <button wire:click="sync" wire:loading.attr="disabled"
                class="mt-5 rounded-lg bg-[#f38c00] px-5 py-2.5 text-[13px] font-semibold text-white hover:bg-[#e07d00] disabled:opacity-50">
            <span wire:loading.remove>Sync Now</span>
            <span wire:loading>Syncing…</span>
        </button>

        @if ($lastResult)
            <p class="mt-4 text-[13px] {{ $lastResultIsError ? 'text-[#dc2626]' : 'text-[#16a34a]' }}">{{ $lastResult }}</p>
        @endif
    </div>

    <a href="{{ route('admin.smart-room.devices') }}" wire:navigate
       class="flex w-fit items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to Smart Devices
    </a>
</div>
