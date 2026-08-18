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

    {{-- ===== Scenes ===== --}}
    @if ($scenes->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m14.5-6.5-2.1 2.1M8.6 15.4l-2.1 2.1m0-11 2.1 2.1m8.8 8.8 2.1 2.1"/><circle cx="12" cy="12" r="4"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No scenes yet</p>
            <p class="text-[13px] text-[#6b7280]">{{ $scopeFilter !== 'all' ? 'Try a different filter.' : 'Create a scene to group smart-device actions into one tap.' }}</p>
        </div>
    @else
        {{-- Scenes + pagination (one card, matches Bookings) --}}
        <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
            <div class="p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($scenes as $scene)
                        <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-5">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $scene->name }}</p>
                                    <p class="text-[12px] text-[#6b7280]">
                                        {{ $scene->room ? 'Category: '.$scene->room->name : ($scene->roomUnit ? 'Room '.$scene->roomUnit->number : '—') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-[#dcfce7] text-[#16a34a]' => $scene->is_active, 'bg-[#f1f1ee] text-[#6b7280]' => ! $scene->is_active])>
                                        {{ $scene->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                    @include('admin.smart-room.partials.scene-actions', ['scene' => $scene])
                                </div>
                            </div>
                            <ul class="flex flex-col gap-1 text-[12px] text-[#374151]">
                                @forelse ($scene->actions as $action)
                                    <li>• {{ $action->device?->name ?? 'Deleted device' }} — {{ json_encode($action->command) }}</li>
                                @empty
                                    <li class="text-[#9ca3af]">No actions configured.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer / pagination (inside the same card, matches Bookings) --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $scenes->firstItem() }}–{{ $scenes->lastItem() }} of {{ number_format($scenes->total()) }} scenes
                </p>
                @if ($scenes->hasPages())
                    @php
                        $last = $scenes->lastPage();
                        $cur = $scenes->currentPage();
                        $start = max(1, min($cur - 1, $last - 2));
                        $end = min($last, $start + 2);
                    @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($scenes->onFirstPage())
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
                        <button type="button" wire:click="nextPage" @disabled(! $scenes->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===================== New/Edit Scene modal (matches New Booking modal) ===================== --}}
    <div x-data="{ show: @entangle('showForm') }" x-show="show" x-cloak x-transition.opacity
         class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto bg-black/50 p-4 sm:p-6"
         @keydown.escape.window="show = false">
        <div class="absolute inset-0" @click="show = false"></div>
        <div class="relative z-10 my-auto w-full max-w-[680px] rounded-2xl bg-white p-6 shadow-xl sm:p-7" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[1px] text-[#f38c00]">Smart Room</p>
                    <h2 class="text-[19px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Scene' : 'New Scene' }}</h2>
                    <p class="mt-0.5 text-[12px] text-[#6b7280]">A one-tap group of smart-device actions.</p>
                </div>
                <button type="button" @click="show = false" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f3f4f6]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="mt-5 flex max-h-[70vh] flex-col gap-4 overflow-y-auto pr-1">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Name</label>
                        <input type="text" wire:model="fName" placeholder="e.g. Welcome"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Slug</label>
                        <input type="text" wire:model="fSlug" placeholder="e.g. welcome"
                               class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('fSlug') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Icon key <span class="font-normal normal-case text-[#9ca3af]">(optional)</span></label>
                    <input type="text" wire:model="fIcon" placeholder="e.g. sunny"
                           class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                </div>

                <div class="flex gap-5 text-[13px] text-[#374151]">
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="fScopeType" value="room_unit" class="text-[#f38c00] focus:ring-[#f38c00]/30">
                        Room-specific
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="fScopeType" value="room" class="text-[#f38c00] focus:ring-[#f38c00]/30">
                        Room category template
                    </label>
                </div>

                @if ($fScopeType === 'room_unit')
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Room</label>
                        <select wire:model="fRoomUnitId"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">— Select —</option>
                            @foreach ($roomUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->number }}</option>
                            @endforeach
                        </select>
                        @error('fRoomUnitId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                @else
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Room Category</label>
                        <select wire:model="fRoomId"
                                class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">— Select —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}">{{ $room->name }}</option>
                            @endforeach
                        </select>
                        @error('fRoomId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Actions</label>
                        <button type="button" wire:click="addAction" class="text-[12px] font-semibold text-[#f38c00] hover:underline">+ Add action</button>
                    </div>
                    <div class="flex flex-col gap-2">
                        @foreach ($fActions as $i => $action)
                            <div class="flex items-center gap-2">
                                <select wire:model="fActions.{{ $i }}.smart_device_id"
                                        class="h-10 flex-1 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                    <option value="">Device…</option>
                                    @foreach ($devices as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" wire:model="fActions.{{ $i }}.capability" placeholder="capability (e.g. power)"
                                       class="h-10 w-32 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <input type="text" wire:model="fActions.{{ $i }}.value" placeholder="value"
                                       class="h-10 w-24 rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <button type="button" wire:click="removeAction({{ $i }})" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-[#dc2626] transition hover:bg-[#fef2f2]">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                        @if (empty($fActions))
                            <p class="text-[12px] text-[#9ca3af]">No actions yet — add one above.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-2 flex justify-end gap-3">
                    <button type="button" @click="show = false" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-4 py-2.5 text-[13px] font-semibold text-white transition hover:bg-[#e07d00]">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
