<div class="flex flex-col gap-4">

    {{-- Back --}}
    <a href="{{ route('admin.smart-room.dashboard') }}" wire:navigate
       class="flex w-fit items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to Smart Room
    </a>

    {{-- ===== Filters ===== --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#e5e7eb] bg-white px-4 py-3">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search devices…"
               class="w-56 rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
        <select wire:model.live="typeFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="">All statuses</option>
            <option value="online">Online</option>
            <option value="offline">Offline</option>
            <option value="unknown">Unknown</option>
        </select>
        <select wire:model.live="roomFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="">All rooms</option>
            <option value="unassigned">Unassigned</option>
        </select>
        <a href="{{ route('admin.smart-room.sync') }}" wire:navigate class="ml-auto rounded-lg bg-[#f38c00] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#e07d00]">Sync from Tuya</a>
    </div>

    {{-- ===== Table + pagination (one card, matches Bookings) ===== --}}
    <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-[13px]">
            <thead class="border-b border-[#e5e7eb] text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">
                <tr>
                    <th class="px-5 py-3">Device</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Room</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Active</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#f1f1ee]">
                @forelse ($devices as $device)
                    <tr>
                        <td class="px-5 py-3 font-medium text-[#1e1e1e]">{{ $device->name }}</td>
                        <td class="px-5 py-3 text-[#374151]">{{ ucfirst($device->type) }}</td>
                        <td class="px-5 py-3 text-[#374151]">
                            {{ $device->roomUnit ? 'Room '.$device->roomUnit->number : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                'bg-[#dcfce7] text-[#16a34a]' => $device->status === 'online',
                                'bg-[#f1f1ee] text-[#6b7280]' => $device->status === 'offline',
                                'bg-[#fef3c7] text-[#d97706]' => $device->status === 'unknown',
                            ])>{{ ucfirst($device->status) }}</span>
                        </td>
                        <td class="px-5 py-3">{{ $device->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-5 py-3 text-right">
                            @include('admin.smart-room.partials.device-actions', ['device' => $device])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-[13px] text-[#9ca3af]">No smart devices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        {{-- Footer / pagination (inside the same card, matches Bookings) --}}
        <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[12px] text-[#6b7280]">
                Showing {{ $devices->firstItem() ?? 0 }}–{{ $devices->lastItem() ?? 0 }} of {{ number_format($devices->total()) }} devices
            </p>
            @if ($devices->hasPages())
                @php
                    $last = $devices->lastPage();
                    $cur = $devices->currentPage();
                    $start = max(1, min($cur - 1, $last - 2));
                    $end = min($last, $start + 2);
                @endphp
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

    {{-- ===================== Edit Device modal (matches New Booking modal) ===================== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[680px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Smart Room</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">Edit Device</h2>
                    <p class="mt-0.5 text-[12px] text-[#6b7280]">Rename this device or assign it to a room.</p>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Name</label>
                    <input type="text" wire:model="fName" placeholder="e.g. Main Light"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Suite</label>
                        <select wire:model.live="fSuiteId"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">— None —</option>
                            @foreach ($suites as $suite)
                                <option value="{{ $suite->id }}">{{ $suite->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Room Number</label>
                        <select wire:model="fRoomUnitId"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">— Unassigned —</option>
                            @foreach ($formRoomUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->number }}</option>
                            @endforeach
                        </select>
                        @error('fRoomUnitId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-[13px] text-[#374151]">
                    <input type="checkbox" wire:model="fIsActive" class="size-4 rounded border-[#e5e7eb] text-[#f38c00] focus:ring-[#f38c00]/30">
                    Active
                </label>

                <div class="mt-2 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-4 py-2.5 text-[13px] font-semibold text-white transition hover:bg-[#e07d00]">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
