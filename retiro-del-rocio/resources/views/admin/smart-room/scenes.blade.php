<div class="flex flex-col gap-4">

    {{-- Back --}}
    <a href="{{ route('admin.smart-room.dashboard') }}" wire:navigate
       class="flex w-fit items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Back to Smart Room
    </a>

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#e5e7eb] bg-white px-4 py-3">
        <select wire:model.live="scopeFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="all">All scenes</option>
            <option value="room">Room category templates</option>
            <option value="room_unit">Room-specific</option>
        </select>
        <button wire:click="openCreate" class="ml-auto rounded-lg bg-[#f38c00] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#e07d00]">New Scene</button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($scenes as $scene)
            <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $scene->name }}</p>
                        <p class="text-[12px] text-[#6b7280]">
                            {{ $scene->room ? 'Category: '.$scene->room->name : ($scene->roomUnit ? 'Room '.$scene->roomUnit->number : '—') }}
                        </p>
                    </div>
                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-[#dcfce7] text-[#16a34a]' => $scene->is_active, 'bg-[#f1f1ee] text-[#6b7280]' => ! $scene->is_active])>
                        {{ $scene->is_active ? 'Active' : 'Disabled' }}
                    </span>
                </div>
                <ul class="flex flex-col gap-1 text-[12px] text-[#374151]">
                    @forelse ($scene->actions as $action)
                        <li>• {{ $action->device?->name ?? 'Deleted device' }} — {{ json_encode($action->command) }}</li>
                    @empty
                        <li class="text-[#9ca3af]">No actions configured.</li>
                    @endforelse
                </ul>
                <div class="mt-2 flex justify-end gap-2">
                    <button wire:click="toggleActive({{ $scene->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] hover:bg-[#f9fafb]">{{ $scene->is_active ? 'Disable' : 'Enable' }}</button>
                    <button wire:click="edit({{ $scene->id }})" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#374151] hover:bg-[#f9fafb]">Edit</button>
                    <button wire:click="delete({{ $scene->id }})" wire:confirm="Delete this scene?" class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-semibold text-[#dc2626] hover:bg-[#fee2e2]">Delete</button>
                </div>
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-[13px] text-[#9ca3af]">No scenes yet.</p>
        @endforelse
    </div>

    <div>{{ $scenes->links() }}</div>

    {{-- ===== Create / Edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-8" wire:click.self="$set('showForm', false)">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6">
                <p class="mb-4 text-[16px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Scene' : 'New Scene' }}</p>

                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-[12px] font-medium text-[#374151]">Name</label>
                            <input type="text" wire:model="fName" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                            @error('fName') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-[12px] font-medium text-[#374151]">Slug</label>
                            <input type="text" wire:model="fSlug" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                            @error('fSlug') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[12px] font-medium text-[#374151]">Icon key (optional)</label>
                        <input type="text" wire:model="fIcon" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                    </div>

                    <div class="flex gap-4 text-[13px]">
                        <label class="flex items-center gap-2"><input type="radio" wire:model.live="fScopeType" value="room_unit"> Room-specific</label>
                        <label class="flex items-center gap-2"><input type="radio" wire:model.live="fScopeType" value="room"> Room category template</label>
                    </div>

                    @if ($fScopeType === 'room_unit')
                        <div>
                            <label class="mb-1 block text-[12px] font-medium text-[#374151]">Room</label>
                            <select wire:model="fRoomUnitId" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                                <option value="">— Select —</option>
                                @foreach ($roomUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->number }}</option>
                                @endforeach
                            </select>
                            @error('fRoomUnitId') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-[12px] font-medium text-[#374151]">Room Category</label>
                            <select wire:model="fRoomId" class="w-full rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
                                <option value="">— Select —</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </select>
                            @error('fRoomId') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-[12px] font-medium text-[#374151]">Actions</label>
                            <button type="button" wire:click="addAction" class="text-[12px] font-semibold text-[#f38c00] hover:underline">+ Add action</button>
                        </div>
                        <div class="flex flex-col gap-2">
                            @foreach ($fActions as $i => $action)
                                <div class="flex items-center gap-2">
                                    <select wire:model="fActions.{{ $i }}.smart_device_id" class="flex-1 rounded-lg border border-[#e5e7eb] px-2 py-1.5 text-[12px]">
                                        <option value="">Device…</option>
                                        @foreach ($devices as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="fActions.{{ $i }}.capability" placeholder="capability (e.g. power)" class="w-32 rounded-lg border border-[#e5e7eb] px-2 py-1.5 text-[12px]">
                                    <input type="text" wire:model="fActions.{{ $i }}.value" placeholder="value" class="w-24 rounded-lg border border-[#e5e7eb] px-2 py-1.5 text-[12px]">
                                    <button type="button" wire:click="removeAction({{ $i }})" class="text-[#dc2626]">✕</button>
                                </div>
                            @endforeach
                            @if (empty($fActions))
                                <p class="text-[12px] text-[#9ca3af]">No actions yet — add one above.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="$set('showForm', false)" class="rounded-lg border border-[#e5e7eb] px-4 py-2 text-[13px] font-semibold text-[#374151]">Cancel</button>
                    <button wire:click="save" class="rounded-lg bg-[#f38c00] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#e07d00]">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
