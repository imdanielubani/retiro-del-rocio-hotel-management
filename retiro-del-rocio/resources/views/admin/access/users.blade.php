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
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or email…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <select wire:model.live="roleFilter" class="h-10 rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 text-[13px] text-[#374151] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
                <option value="">All roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->name }}">{{ Str::headline($r->name) }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'active' => 'Active', 'suspended' => 'Suspended'] as $key => $label)
                    <button type="button" wire:click="setStatus(@js($key))"
                            @class([
                                'rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $users->count() }}</span> {{ Str::plural('user', $users->count()) }}</p>
            <button type="button" wire:click="openCreate" class="flex h-10 shrink-0 items-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add User
            </button>
        </div>
    </div>

    {{-- ===== Users table ===== --}}
    @if ($users->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No users found</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add User</button>
        </div>
    @else
        @php $initialsOf = fn ($name) => Str::of($name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode(''); @endphp
        <div class="rounded-2xl border border-[#e5e7eb] bg-white">
            {{-- Table (large screens) --}}
            <table class="hidden w-full border-collapse text-left xl:table">
                <thead class="border-b border-[#e5e7eb] bg-[#f9fafb]">
                    <tr class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">
                        <th class="px-4 py-3 font-semibold">User</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Roles</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Last login</th>
                        <th class="px-4 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr wire:key="user-row-{{ $user->id }}" class="border-b border-[#f1f1ee] last:border-0">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#fff4e5] text-[12px] font-bold uppercase text-[#f38c00]">{{ $initialsOf($user->name) ?: 'U' }}</div>
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $user->name }}</p>
                                        <p class="truncate text-[12px] text-[#6b7280]">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $user->phone ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $r)
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.3px]',
                                            'bg-[#f3e8ff] text-[#7c3aed]' => $r->name === 'super-admin',
                                            'bg-[#fff4e5] text-[#b45309]' => $r->name !== 'super-admin',
                                        ])>{{ Str::headline($r->name) }}</span>
                                    @empty
                                        <span class="text-[12px] text-[#9ca3af]">No roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $user->status === 'active' ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                                    <span class="size-[5px] rounded-full {{ $user->status === 'active' ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ Str::headline($user->status ?: 'active') }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                @include('admin.access.partials.user-actions', ['user' => $user])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Cards (small screens) --}}
            <div class="divide-y divide-[#f1f1ee] xl:hidden">
                @foreach ($users as $user)
                    <div wire:key="user-card-{{ $user->id }}" class="flex items-start gap-3 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#fff4e5] text-[13px] font-bold uppercase text-[#f38c00]">{{ $initialsOf($user->name) ?: 'U' }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-[14px] font-semibold text-[#1e1e1e]">{{ $user->name }}</p>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $user->status === 'active' ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">{{ Str::headline($user->status ?: 'active') }}</span>
                            </div>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $user->email }}</p>
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @forelse ($user->roles as $r)
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.3px]',
                                        'bg-[#f3e8ff] text-[#7c3aed]' => $r->name === 'super-admin',
                                        'bg-[#fff4e5] text-[#b45309]' => $r->name !== 'super-admin',
                                    ])>{{ Str::headline($r->name) }}</span>
                                @empty
                                    <span class="text-[11px] text-[#9ca3af]">No roles</span>
                                @endforelse
                            </div>
                        </div>
                        @include('admin.access.partials.user-actions', ['user' => $user])
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Add / edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 my-auto flex max-h-[88vh] w-full max-w-[560px] flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit User' : 'Add User' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Full name</label>
                            <input type="text" wire:model="fName" placeholder="e.g. Jane Doe" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Email</label>
                            <input type="email" wire:model="fEmail" placeholder="jane@retirodelrocio.com" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fEmail') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Phone <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="text" wire:model="fPhone" placeholder="+234…" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Password {{ $editingId ? '' : '' }}<span class="font-normal text-[#9ca3af]">{{ $editingId ? ' (leave blank to keep)' : '' }}</span></label>
                            <input type="password" wire:model="fPassword" placeholder="At least 8 characters" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fPassword') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Status</label>
                            <select wire:model="fStatus" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Roles</label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($roles as $r)
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-2.5">
                                        <input type="checkbox" wire:model="fRoles" value="{{ $r->name }}" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                                        <span class="text-[13px] text-[#374151]">{{ Str::headline($r->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('fRoles.*') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save changes' : 'Create user' }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
