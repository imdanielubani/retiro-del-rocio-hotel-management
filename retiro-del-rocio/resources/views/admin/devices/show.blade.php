<div class="flex flex-col gap-4">

    {{-- Back --}}
    <a href="{{ route('admin.devices.tablets') }}" wire:navigate class="inline-flex w-fit items-center gap-1.5 text-[13px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to devices
    </a>

    {{-- Header --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="flex items-center gap-4">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-[#fff3e0] text-[#f38c00]">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M10 18h4"/></svg>
            </span>
            <div>
                <h2 class="text-[20px] font-bold text-[#1e1e1e]">{{ $device->device_name }}</h2>
                <p class="text-[13px] font-medium text-[#f38c00]">{{ $device->device_code }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-medium {{ implode(' ', $device->status->badge()) }}">
                <span class="size-1.5 rounded-full {{ $device->status->dot() }}"></span>
                {{ $device->status->label() }}
            </span>
            <span class="inline-flex items-center rounded-full border border-[#e5e7eb] px-3 py-1.5 text-[12px] text-[#6b7280]">{{ $device->type->name }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Details --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            @php
                $groups = [
                    'General Information' => [
                        'Device Name' => $device->device_name,
                        'Device Code' => $device->device_code,
                        'Type' => $device->type->name,
                        'Manufacturer' => $device->manufacturer ?: '—',
                        'Model' => $device->model ?: '—',
                        'Serial Number' => $device->serial_number ?: '—',
                    ],
                    'Network Information' => [
                        'IP Address' => $device->ip_address ?: 'Not reported',
                        'MAC Address' => $device->mac_address ?: 'Not reported',
                        'WiFi Strength' => $device->wifi_strength !== null ? $device->wifi_strength.'%' : 'Not reported',
                    ],
                    'Telemetry' => [
                        'Battery' => $device->battery_level !== null ? $device->battery_level.'%' : 'Not reported',
                        'Last Seen (Heartbeat)' => $device->last_seen_at?->format('M j, Y g:i A') ?? 'Never',
                        'Last Sync' => $device->last_sync_at?->format('M j, Y g:i A') ?? 'Never',
                        'Installed App Version' => $device->app_version ?: 'Not reported',
                        'Android Version' => $device->android_version ?: 'Not reported',
                    ],
                    'Assignment' => array_filter([
                        'Mode' => $device->mode->label(),
                        ($device->isStaff() ? 'Locked Role' : 'Room Number') => $device->allocationLabel() ?? 'Unassigned',
                        'Suite' => $device->isGuest() ? (optional($device->room)->name ?? '—') : null,
                        'Category' => $device->isGuest() ? (optional($device->room)->type ?? '—') : null,
                        'Current Guest' => $device->isGuest() ? ($currentGuest ?? '—') : null,
                        'Provisioned' => $device->is_provisioned ? 'Yes' : 'No',
                        'Provision Date' => $device->provisioned_at?->format('M j, Y') ?? '—',
                        'Registered By' => optional($device->creator)->name ?? '—',
                    ], fn ($v) => $v !== null),
                ];
            @endphp

            @foreach ($groups as $heading => $rows)
                <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $heading }}</p>
                    <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        @foreach ($rows as $label => $value)
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-[11px] uppercase tracking-[0.4px] text-[#9ca3af]">{{ $label }}</dt>
                                <dd class="text-[14px] font-medium text-[#1e1e1e]">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach

            @if ($device->notes)
                <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 sm:p-6">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Notes</p>
                    <p class="mt-2 text-[14px] leading-relaxed text-[#374151]">{{ $device->notes }}</p>
                </div>
            @endif
        </div>

        {{-- QR --}}
        <div class="flex flex-col gap-4">
            <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5 text-center sm:p-6">
                <p class="text-[15px] font-bold text-[#1e1e1e]">Provisioning QR</p>
                <div class="mx-auto mt-4 w-fit rounded-2xl border border-[#e5e7eb] p-3">{!! $qrSvg !!}</div>
                <p class="mt-3 text-[12px] leading-relaxed text-[#6b7280]">Scan with the Rocio Tablet app to bind this device. Carries the device code, room and provision token.</p>
                @if ($device->is_provisioned)
                    <span class="mt-3 inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-1 text-[11px] font-medium text-[#16a34a]">Provisioned {{ $device->provisioned_at?->format('M j, Y') }}</span>
                @else
                    <span class="mt-3 inline-flex items-center rounded-full bg-[#e0f2fe] px-2.5 py-1 text-[11px] font-medium text-[#0369a1]">Awaiting provisioning</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Activity logs --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        <div class="border-b border-[#e5e7eb] px-5 py-4">
            <p class="text-[15px] font-bold text-[#1e1e1e]">Activity Logs</p>
        </div>
        @if ($logs->isEmpty())
            <div class="px-5 py-12 text-center"><p class="text-[13px] text-[#9ca3af]">No activity recorded yet.</p></div>
        @else
            <table class="hidden w-full border-collapse text-left sm:table">
                <thead>
                    <tr>
                        @foreach (['Event', 'Description', 'By', 'When'] as $col)
                            <th class="border-b border-[#e5e7eb] bg-[#f9fafb] px-5 py-3 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f1ee]">
                    @foreach ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="px-5 py-3"><span class="inline-flex items-center rounded-md bg-[#f3f4f6] px-2 py-0.5 text-[12px] font-medium text-[#374151]">{{ $log->eventLabel() }}</span></td>
                            <td class="px-5 py-3 text-[13px] text-[#374151]">{{ $log->description ?: '—' }}</td>
                            <td class="px-5 py-3 text-[13px] text-[#6b7280]">{{ optional($log->user)->name ?? 'System' }}</td>
                            <td class="px-5 py-3 text-[12px] text-[#9ca3af]">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="flex flex-col divide-y divide-[#f1f1ee] sm:hidden">
                @foreach ($logs as $log)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center rounded-md bg-[#f3f4f6] px-2 py-0.5 text-[12px] font-medium text-[#374151]">{{ $log->eventLabel() }}</span>
                            <span class="text-[12px] text-[#9ca3af]">{{ $log->created_at->format('M j, g:i A') }}</span>
                        </div>
                        @if ($log->description)<p class="mt-1 text-[13px] text-[#374151]">{{ $log->description }}</p>@endif
                    </div>
                @endforeach
            </div>
            @if ($logs->hasPages())
                <div class="border-t border-[#e5e7eb] px-5 py-3">{{ $logs->links() }}</div>
            @endif
        @endif
    </div>
</div>
