<div class="flex flex-col gap-4">

    {{-- ===== Summary cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $card['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $card['label'] }}</p>
                <p class="text-[clamp(20px,2vw,26px)] font-medium leading-tight text-[#1e1e1e]">{{ $card['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $card['accent'] }}">{{ $card['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Charts ===== --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Devices by Type --}}
        <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
            <p class="text-[15px] font-bold text-[#1e1e1e]">Devices by Type</p>
            <div class="mt-5 flex flex-col gap-4">
                @forelse ($byType as $row)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-[13px]">
                            <span class="font-medium text-[#374151]">{{ $row['label'] }}</span>
                            <span class="text-[#6b7280]">{{ $row['value'] }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-[#f1f1ee]">
                            <div class="h-full rounded-full bg-[#f38c00]" style="width: {{ $row['value'] ? max(4, round($row['value'] / $byTypeMax * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-[13px] text-[#9ca3af]">No device types yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Devices by Status --}}
        <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
            <p class="text-[15px] font-bold text-[#1e1e1e]">Devices by Status</p>
            @if ($byStatus->isEmpty())
                <p class="mt-5 text-[13px] text-[#9ca3af]">No devices registered yet.</p>
            @else
                {{-- Stacked bar --}}
                <div class="mt-5 flex h-3 overflow-hidden rounded-full bg-[#f1f1ee]">
                    @foreach ($byStatus as $row)
                        <div class="h-full" style="width: {{ round($row['value'] / $byStatusTotal * 100) }}%; background: {{ $row['hex'] }}"></div>
                    @endforeach
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2">
                    @foreach ($byStatus as $row)
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background: {{ $row['hex'] }}"></span>
                                <span class="text-[#374151]">{{ $row['label'] }}</span>
                            </span>
                            <span class="font-medium text-[#1e1e1e]">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Recent Device Activity ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
            <p class="text-[15px] font-bold text-[#1e1e1e]">Recent Device Activity</p>
            <a href="{{ route('admin.devices.tablets') }}" wire:navigate class="text-[12px] font-semibold text-[#f38c00] hover:underline">View tablets</a>
        </div>
        @if ($recent->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-[13px] text-[#9ca3af]">No device activity yet.</p>
            </div>
        @else
            <div class="divide-y divide-[#f1f1ee]">
                @foreach ($recent as $log)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#fff3e0] text-[#f38c00]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] text-[#1e1e1e]">
                                <span class="font-semibold">{{ $log->eventLabel() }}</span>
                                @if ($log->device) · <a href="{{ route('admin.devices.show', $log->device) }}" wire:navigate class="text-[#f38c00] hover:underline">{{ $log->device->device_name }}</a>@endif
                            </p>
                            @if ($log->description)<p class="truncate text-[12px] text-[#9ca3af]">{{ $log->description }}</p>@endif
                        </div>
                        <span class="shrink-0 text-[12px] text-[#9ca3af]">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
