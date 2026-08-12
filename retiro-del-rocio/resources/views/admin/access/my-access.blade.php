@php use Illuminate\Support\Str; @endphp
<div class="flex flex-col gap-4">
    {{-- ===== Roles ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5">
        <h3 class="text-[15px] font-bold text-[#1e1e1e]">Your role{{ count($roles) === 1 ? '' : 's' }}</h3>
        <p class="mt-0.5 text-[12px] text-[#6b7280]">Granted by a Super Admin or Admin. Ask them if this needs to change.</p>

        <div class="mt-4 flex flex-wrap gap-2">
            @forelse ($roles as $role)
                <span class="inline-flex items-center gap-2 rounded-full bg-[#fff4e5] px-3.5 py-1.5 text-[13px] font-semibold text-[#f38c00]">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6z"/></svg>
                    {{ Str::headline($role) }}
                </span>
            @empty
                <p class="text-[13px] text-[#9ca3af]">No role assigned yet — contact a Super Admin or Admin.</p>
            @endforelse
        </div>

        @if ($isSuperAdmin)
            <div class="mt-4 rounded-xl border border-[#e0f2fe] bg-[#f0f9ff] px-4 py-3 text-[12.5px] text-[#0369a1]">
                Super Admin has every permission in the system — nothing is restricted for this account.
            </div>
        @endif
    </div>

    {{-- ===== Permissions ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-bold text-[#1e1e1e]">What you can access</h3>
                <p class="text-[12px] text-[#6b7280]">{{ $permissionCount }} {{ Str::plural('permission', $permissionCount) }} on your account</p>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-4">
            @forelse ($grouped as $group => $perms)
                <div class="flex flex-col gap-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.5px] text-[#9ca3af]">{{ $group }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($perms as $perm)
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#bbf7d0] bg-[#f0fdf4] px-2.5 py-1 text-[12px] text-[#16a34a]">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $perm }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-[13px] text-[#9ca3af]">Your account has no permissions yet — contact a Super Admin or Admin.</p>
            @endforelse
        </div>
    </div>
</div>
