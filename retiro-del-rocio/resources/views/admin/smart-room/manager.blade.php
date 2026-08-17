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

    {{-- ===== Table ===== --}}
    <div class="overflow-x-auto rounded-2xl border border-[#e5e7eb] bg-white">
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
                            <div class="flex justify-end gap-2">
                                <button wire:click="testConnection({{ $device->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] hover:bg-[#f9fafb]">Test</button>
                                <button wire:click="edit({{ $device->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] hover:bg-[#f9fafb]">Edit</button>
                                <button wire:click="toggleActive({{ $device->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] hover:bg-[#f9fafb]">{{ $device->is_active ? 'Disable' : 'Enable' }}</button>
                                @if ($device->room_unit_id)
                                    <button wire:click="unassign({{ $device->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#dc2626] hover:bg-[#fee2e2]">Unassign</button>
                                @endif
                            </div>
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

    <div>{{ $devices->links() }}</div>

    {{-- ===== Edit / Assign modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" wire:click.self="$set('showForm', false)">
            <div class="w-full max-w-md rounded-2xl bg-white p-6">
                <p class="mb-4 text-[16px] font-bold text-[#1e1e1e]">Edit Device</p>
                <div class="flex flex-col gap-4">
                    <div>
                        <label class="mb-1 block text-[12px] font-medium text-[#374151]">Name</label>
                        <input type="text" wire:model="fName" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                        @error('fName') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-[12px] font-medium text-[#374151]">Suite</label>
                        <select wire:model.live="fSuiteId" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                            <option value="">— None —</option>
                            @foreach ($suites as $suite)
                                <option value="{{ $suite->id }}">{{ $suite->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[12px] font-medium text-[#374151]">Room Number</label>
                        <select wire:model="fRoomUnitId" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                            <option value="">— Unassigned —</option>
                            @foreach ($formRoomUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->number }}</option>
                            @endforeach
                        </select>
                        @error('fRoomUnitId') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-[13px]">
                        <input type="checkbox" wire:model="fIsActive"> Active
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-[#e5e7eb] px-4 py-2 text-[13px] font-semibold text-[#374151]">Cancel</button>
                    <button wire:click="save" class="rounded-lg bg-[#f38c00] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#e07d00]">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
