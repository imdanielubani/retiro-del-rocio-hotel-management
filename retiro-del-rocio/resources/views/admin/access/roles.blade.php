@php use Illuminate\Support\Str; @endphp
<div class="flex flex-col gap-4">
    @if (session('access_status'))
        <div class="rounded-xl border border-[#bbf7d0] bg-[#f0fdf4] px-4 py-2.5 text-[13px] font-medium text-[#16a34a]">{{ session('access_status') }}</div>
    @endif

    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 sm:flex-row sm:items-center">
        <div class="relative w-full sm:w-[260px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search roles…"
                   class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
        <div class="flex items-center justify-between gap-3 sm:ml-auto">
            <p class="whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $roles->count() }}</span> {{ Str::plural('role', $roles->count()) }}</p>
            <button type="button" wire:click="openCreate" class="flex h-10 items-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Role
            </button>
        </div>
    </div>

    {{-- ===== Roles grid ===== --}}
    @if ($roles->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No roles found</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Role</button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($roles as $role)
                @php $isProtected = in_array($role->name, $protected, true); @endphp
                <div wire:key="role-{{ $role->id }}" class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="flex size-10 items-center justify-center rounded-xl bg-[#fff4e5] text-[#f38c00]">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5l-8-3Z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-[15px] font-bold text-[#1e1e1e]">{{ Str::headline($role->name) }}</h3>
                                @if ($isProtected)
                                    <span class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-[#f3e8ff] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.4px] text-[#7c3aed]">Protected</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="edit({{ $role->id }})" title="Edit" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee] hover:text-[#f38c00]">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                            @unless ($isProtected)
                                <button type="button" wire:click="delete({{ $role->id }})" wire:confirm="Delete the “{{ Str::headline($role->name) }}” role? Users with only this role will lose its access." title="Delete" class="flex size-8 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#fef2f2] hover:text-[#dc2626]">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                </button>
                            @endunless
                        </div>
                    </div>
                    <div class="flex items-center gap-4 border-t border-[#f1f1ee] pt-3 text-[12px] text-[#6b7280]">
                        <span class="inline-flex items-center gap-1.5"><span class="font-semibold text-[#1e1e1e]">{{ $role->users_count }}</span> {{ Str::plural('user', $role->users_count) }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="font-semibold text-[#1e1e1e]">{{ $isProtected ? 'All' : $role->permissions_count }}</span> {{ Str::plural('permission', $role->permissions_count) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Permissions reference ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-bold text-[#1e1e1e]">Permissions</h3>
                <p class="text-[12px] text-[#6b7280]">Assign these to roles above</p>
            </div>
            <button type="button" wire:click="openCreatePermission" class="flex h-9 items-center gap-1.5 rounded-lg border border-[#e5e7eb] px-3.5 text-[13px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Permission
            </button>
        </div>
        <div class="mt-4 flex flex-col gap-4">
            @foreach ($grouped as $group => $perms)
                <div class="flex flex-col gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $group }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($perms as $perm)
                            <span class="rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-2.5 py-1 text-[12px] text-[#374151]">{{ $perm->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== Role modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 my-auto flex max-h-[86vh] w-full max-w-[620px] flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Role' : 'Add Role' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Role name</label>
                        @php $lockName = $editingId && in_array($fName, $protected, true); @endphp
                        <input type="text" wire:model="fName" @disabled($lockName) placeholder="e.g. front-desk"
                               class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15 disabled:bg-[#f9fafb] disabled:text-[#9ca3af]">
                        @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-[#9ca3af]">Lowercase; spaces become hyphens.</p>
                    </div>

                    @if ($lockName)
                        <div class="mt-5 rounded-xl border border-[#e0f2fe] bg-[#f0f9ff] px-4 py-3 text-[12.5px] text-[#0369a1]">The <strong>super-admin</strong> role always has every permission and can't be limited.</div>
                    @else
                        <div class="mt-5 flex flex-col gap-4">
                            <p class="text-[12px] font-semibold text-[#374151]">Permissions</p>
                            @foreach ($grouped as $group => $perms)
                                @php $names = $perms->pluck('name')->all(); @endphp
                                <div class="rounded-xl border border-[#e5e7eb] p-3.5">
                                    <div class="mb-2.5 flex items-center justify-between">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">{{ $group }}</p>
                                        <button type="button" wire:click="toggleGroup(@js($names))" class="text-[11px] font-semibold text-[#f38c00] hover:underline">Toggle all</button>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($perms as $perm)
                                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 py-2">
                                                <input type="checkbox" wire:model="fPermissions" value="{{ $perm->name }}" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                                                <span class="text-[13px] text-[#374151]">{{ $perm->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save changes' : 'Create role' }}</button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== Permission modal ===== --}}
    @if ($showPermForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showPermForm', false)"></div>
            <form wire:submit="savePermission" class="relative z-10 my-auto w-full max-w-[460px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">Add Permission</h3>
                    <button type="button" wire:click="$set('showPermForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="px-6 py-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Permission name</label>
                        <input type="text" wire:model="pName" placeholder="e.g. manage reports" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('pName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-[#9ca3af]">Use a consistent style, e.g. “manage reports” or “reports.view”.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showPermForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Add permission</button>
                </div>
            </form>
        </div>
    @endif
</div>
