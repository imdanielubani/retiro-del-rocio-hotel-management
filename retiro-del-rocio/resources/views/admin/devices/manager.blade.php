<div class="flex flex-col gap-4">

    {{-- ===== Stat cards ===== --}}
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

    {{-- ===== Toolbar (matches the Spa & Gym pattern) ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2.5">
            {{-- Search --}}
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, code or serial…"
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-9 pr-4 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>

            {{-- Status dropdown --}}
            <select wire:model.live="statusFilter"
                    class="h-9 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-2.5 text-[12px] text-[#374151] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>

            {{-- Suite dropdown --}}
            <select wire:model.live="roomFilter"
                    class="h-9 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-2.5 text-[12px] text-[#374151] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                <option value="">All suites</option>
                <option value="unassigned">Unassigned</option>
                @foreach ($suites as $suite)
                    <option value="{{ $suite->id }}">{{ $suite->name }}{{ $suite->type ? ' · '.$suite->type : '' }}</option>
                @endforeach
            </select>
        </div>

        {{-- Count + export + register --}}
        <div class="flex items-center justify-between gap-2.5 xl:shrink-0">
            <p class="text-[11px] text-[#6b7280]"><span class="font-bold text-[#1e1e1e]">{{ $devices->total() }}</span> {{ strtolower($plural) }}</p>
            <button type="button" wire:click="export"
                    class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-[#e5e7eb] bg-white px-3.5 text-[12px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3m0 12-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Export
            </button>
            @can("{$permPrefix}.create")
                <button type="button" wire:click="openCreate"
                        class="flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#f38c00] px-4 text-[12px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Register {{ $singular }}
                </button>
            @endcan
        </div>
    </div>

    {{-- ===== Table / list ===== --}}
    @if ($devices->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M10 18h4"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No {{ strtolower($plural) }} found</p>
            <p class="text-[13px] text-[#6b7280]">{{ $search || $statusFilter || $roomFilter ? 'Try a different search or filter.' : 'Register your first '.strtolower($singular).' to get started.' }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-[#e5e7eb] bg-white">
            {{-- Desktop table --}}
            <table class="hidden w-full border-collapse text-left xl:table">
                <thead>
                    <tr>
                        @foreach (['Device Name', 'Device Code', 'Allocation', 'Status', 'Battery', 'WiFi', 'Last Seen', 'Current Guest', 'Action'] as $col)
                            <th class="border-b border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl {{ $col === 'Action' ? 'text-right' : '' }}">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f1ee]">
                    @foreach ($devices as $device)
                        <tr wire:key="dev-{{ $device->id }}" class="transition hover:bg-[#f9fafb]">
                            <td class="px-4 py-3.5">
                                <a href="{{ route('admin.devices.show', $device) }}" wire:navigate class="text-[13px] font-bold text-[#1e1e1e] hover:text-[#f38c00]">{{ $device->device_name }}</a>
                                <p class="text-[12px] text-[#9ca3af]">{{ $device->model ?: $device->manufacturer ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] font-medium text-[#f38c00]">{{ $device->device_code }}</td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-col items-start gap-0.5">
                                    <span class="text-[13px] font-medium text-[#374151]">{{ $device->allocationLabel() ?? '—' }}</span>
                                    @if ($device->isGuest() && $device->room)
                                        <span class="text-[11px] text-[#9ca3af]">{{ $device->room->name }}@if ($device->room->type) · {{ $device->room->type }}@endif</span>
                                    @endif
                                    <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium {{ implode(' ', $device->mode->badge()) }}">{{ $device->mode->label() }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium {{ implode(' ', $device->status->badge()) }}">
                                    <span class="size-1.5 rounded-full {{ $device->status->dot() }}"></span>
                                    {{ $device->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($device->battery_level !== null)
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-12 overflow-hidden rounded-full bg-[#f1f1ee]">
                                            <div class="h-full rounded-full {{ $device->batteryIsLow() ? 'bg-[#dc2626]' : 'bg-[#16a34a]' }}" style="width: {{ $device->battery_level }}%"></div>
                                        </div>
                                        <span class="text-[12px] text-[#6b7280]">{{ $device->battery_level }}%</span>
                                    </div>
                                @else
                                    <span class="text-[12px] text-[#9ca3af]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($device->wifi_strength !== null)
                                    <span class="text-[12px] text-[#6b7280]">{{ $device->wifi_strength }}%</span>
                                @else
                                    <span class="text-[12px] text-[#9ca3af]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $device->currentGuest() ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-right">@include('admin.devices.partials.device-menu', ['device' => $device, 'permPrefix' => $permPrefix])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Mobile / tablet list --}}
            <div class="flex flex-col divide-y divide-[#f1f1ee] xl:hidden">
                @foreach ($devices as $device)
                    <div wire:key="devm-{{ $device->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('admin.devices.show', $device) }}" wire:navigate class="text-[15px] font-bold text-[#1e1e1e]">{{ $device->device_name }}</a>
                                <p class="text-[13px] font-medium text-[#f38c00]">{{ $device->device_code }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ implode(' ', $device->status->badge()) }}">{{ $device->status->label() }}</span>
                                @include('admin.devices.partials.device-menu', ['device' => $device, 'permPrefix' => $permPrefix])
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-[#6b7280]">
                            <span>{{ $device->mode->label() }}: {{ $device->allocationLabel() ?? 'Unassigned' }}</span>
                            <span>Battery: {{ $device->battery_level !== null ? $device->battery_level.'%' : '—' }}</span>
                            <span>Guest: {{ $device->currentGuest() ?? '—' }}</span>
                            <span>Seen: {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $devices->firstItem() }}–{{ $devices->lastItem() }} of {{ number_format($devices->total()) }} {{ strtolower($plural) }}
                </p>
                @if ($devices->hasPages())
                    @php $cur = $devices->currentPage(); $last = $devices->lastPage(); $start = max(1, min($cur - 1, $last - 2)); $end = min($last, $start + 2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($devices->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class([
                                        'flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition',
                                        'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur,
                                        'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur,
                                    ])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $devices->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===================== Register / Edit modal ===================== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data @keydown.escape.window="$wire.set('showForm', false)">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit '.$singular : 'Register '.$singular }}</h2>
                    <button type="button" wire:click="$set('showForm', false)" class="text-[#9ca3af] transition hover:text-[#1e1e1e]">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">{{ $singular }} Name</label>
                            <input type="text" wire:model="fName" placeholder="e.g. Room 101 Tablet" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Device Code</label>
                            <input type="text" wire:model="fCode" placeholder="e.g. TAB-101" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fCode') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        {{-- Allocation: guest → room number, staff → role --}}
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Allocation</label>
                            @if ($supportsStaff)
                                <div class="flex gap-2">
                                    @foreach (['guest' => 'Guest tablet (room)', 'staff' => 'Staff tablet (role)'] as $val => $lbl)
                                        <button type="button" wire:click="$set('fMode', @js($val))"
                                                @class([
                                                    'flex-1 rounded-lg border px-3 py-2 text-[12px] font-medium transition',
                                                    'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $fMode === $val,
                                                    'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $fMode !== $val,
                                                ])>{{ $lbl }}</button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($fMode === 'staff')
                                <select wire:model="fRole" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                    <option value="">Select a role…</option>
                                    @foreach ($staffRoles as $role)
                                        <option value="{{ $role }}">{{ ucfirst(str_replace('-', ' ', $role)) }}</option>
                                    @endforeach
                                </select>
                                @error('fRole') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                                <p class="text-[11px] text-[#9ca3af]">Only staff with this role can sign into the tablet.</p>
                            @else
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <select wire:model.live="fSuiteId" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                        <option value="">Suite (optional)</option>
                                        @foreach ($suites as $suite)
                                            <option value="{{ $suite->id }}">{{ $suite->name }}{{ $suite->type ? ' · '.$suite->type : '' }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="fRoomUnitId" @disabled(! $fSuiteId)
                                            class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15 disabled:bg-[#f9fafb] disabled:text-[#9ca3af]">
                                        <option value="">{{ $fSuiteId ? 'Room number (optional)' : 'Pick a suite first' }}</option>
                                        @foreach ($formRoomUnits as $unit)
                                            <option value="{{ $unit->id }}">Room {{ $unit->number }} · {{ ucfirst($unit->status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('fRoomUnitId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                            @endif
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Status</label>
                            <select wire:model="fStatus" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                @foreach (\App\Enums\DeviceStatus::manuallyAssignable() as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Manufacturer <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="text" wire:model="fManufacturer" placeholder="e.g. Samsung" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Model <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="text" wire:model="fModel" placeholder="e.g. Galaxy Tab A9" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Serial Number <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="text" wire:model="fSerial" placeholder="e.g. RZ8N30ABCDE" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Notes <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <textarea wire:model="fNotes" rows="2" placeholder="Internal notes about this device" class="resize-none rounded-xl border border-[#e5e7eb] px-3.5 py-2.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save changes' : 'Register '.$singular }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===================== Assign modal ===================== --}}
    @if ($showAssign)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data @keydown.escape.window="$wire.set('showAssign', false)">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showAssign', false)"></div>
            <form wire:submit="assign" class="relative z-10 w-full max-w-[440px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">Assign {{ $singular }}</h2>
                    <button type="button" wire:click="$set('showAssign', false)" class="text-[#9ca3af] transition hover:text-[#1e1e1e]">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-6 py-5">
                    @if ($assignMode === 'staff')
                        <label class="text-[12px] font-semibold text-[#374151]">Staff Role</label>
                        <select wire:model="assignRole" class="mt-1.5 h-11 w-full rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">Choose a role…</option>
                            @foreach ($staffRoles as $role)
                                <option value="{{ $role }}">{{ ucfirst(str_replace('-', ' ', $role)) }}</option>
                            @endforeach
                        </select>
                        @error('assignRole') <span class="mt-1 block text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        <p class="mt-2 text-[11px] text-[#9ca3af]">Only staff with this role can sign into the tablet.</p>
                    @else
                        <label class="text-[12px] font-semibold text-[#374151]">Select Room Number</label>
                        <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <select wire:model.live="assignSuiteId" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="">Choose a suite…</option>
                                @foreach ($suites as $suite)
                                    <option value="{{ $suite->id }}">{{ $suite->name }}{{ $suite->type ? ' · '.$suite->type : '' }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="assignRoomUnitId" @disabled(! $assignSuiteId)
                                    class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15 disabled:bg-[#f9fafb] disabled:text-[#9ca3af]">
                                <option value="">{{ $assignSuiteId ? 'Room number' : 'Pick a suite first' }}</option>
                                @foreach ($assignRoomUnits as $unit)
                                    <option value="{{ $unit->id }}">Room {{ $unit->number }} · {{ ucfirst($unit->status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('assignRoomUnitId') <span class="mt-1 block text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror

                        @if ($assignConflict && $assignConflict->id !== $assignId)
                            <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#fcd34d] bg-[#fffbeb] px-3.5 py-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-[#b45309]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                                <p class="text-[12px] leading-relaxed text-[#92400e]">
                                    <strong>{{ $assignConflict->device_name }}</strong> ({{ $assignConflict->device_code }}) is already on this room number. Confirming will move it to <strong>Unassigned</strong>.
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showAssign', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $assignConflict && $assignConflict->id !== $assignId ? 'Replace & Assign' : ($assignMode === 'staff' ? 'Set Role' : 'Assign') }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===================== QR modal ===================== --}}
    @if ($showQr && $qrDevice)
        @php $qrSvg = app(\App\Services\DeviceProvisioningService::class)->qrSvg($qrDevice); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data @keydown.escape.window="$wire.set('showQr', false)">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showQr', false)"></div>
            <div class="relative z-10 w-full max-w-[380px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">Provisioning QR</h2>
                    <button type="button" wire:click="$set('showQr', false)" class="text-[#9ca3af] transition hover:text-[#1e1e1e]">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex flex-col items-center gap-3 px-6 py-6 text-center">
                    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-3">{!! $qrSvg !!}</div>
                    <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $qrDevice->device_code }}</p>
                    <p class="text-[12px] leading-relaxed text-[#6b7280]">Scan with the Rocio Tablet app to bind this {{ strtolower($singular) }}
                        @if ($qrDevice->allocationLabel())as <strong>{{ $qrDevice->allocationLabel() }}</strong>@endif. The QR carries the device code, allocation and a one-time provision token.</p>
                    @if ($qrDevice->is_provisioned)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#dcfce7] px-2.5 py-1 text-[11px] font-medium text-[#16a34a]">Provisioned {{ $qrDevice->provisioned_at?->format('M j, Y') }}</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e0f2fe] px-2.5 py-1 text-[11px] font-medium text-[#0369a1]">Awaiting provisioning</span>
                    @endif
                </div>
                @hasanyrole('super-admin|it-administrator')
                    <div class="border-t border-[#e5e7eb] px-6 py-4">
                        <button type="button" wire:click="regenerateQr({{ $qrDevice->id }})" wire:confirm="Reissue the QR? The current token stops working."
                                class="w-full rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">
                            Regenerate token
                        </button>
                    </div>
                @endhasanyrole
            </div>
        </div>
    @endif
</div>
