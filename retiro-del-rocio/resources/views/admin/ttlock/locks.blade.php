<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Not-configured banner ===== --}}
    @unless ($configured && $gateLockId)
        <div class="rounded-2xl border border-[#fde68a] bg-[#fffbeb] p-4 text-[13px] text-[#92400e]">
            @if (! $configured)
                TTLock isn't configured yet. Add your <code class="font-mono">TTLOCK_*</code> credentials to the <code class="font-mono">.env</code> file.
            @else
                No Access Gate lock is set. Add <code class="font-mono">TTLOCK_LOCK_ID</code> (the gate lock) to the <code class="font-mono">.env</code> file to start issuing gate passes.
            @endif
        </div>
    @endunless

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[260px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest, reference, passcode…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'failed' => 'Failed'] as $key => $label)
                    <button type="button" wire:click="setStatus(@js($key))"
                            @class([
                                'rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <button wire:click="testConnection" wire:loading.attr="disabled"
                class="rounded-lg bg-[#f38c00] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#dd7f00] xl:shrink-0">
            <span wire:loading.remove wire:target="testConnection">Test connection</span>
            <span wire:loading wire:target="testConnection">Testing…</span>
        </button>
    </div>

    {{-- ===== Guest access records ===== --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($passes->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No gate passes yet</p>
                <p class="text-[13px] text-[#6b7280]">Passcodes &amp; QR codes appear here automatically once bookings are confirmed.</p>
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[960px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Guest','Reservation','Stay','Passcode','Status','Action'] as $col)
                                <th @class(['border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]','text-right' => $col === 'Action'])>{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($passes as $b)
                            <tr wire:key="gp-{{ $b->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                                    <p class="text-[11px] text-[#9ca3af]">{{ $b->customer_email ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="text-[13px] font-bold text-[#f38c00]">{{ $b->bookingCode() }}</p>
                                    <p class="text-[11px] text-[#9ca3af]">{{ $b->room_name ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-[12px] text-[#374151]">
                                    {{ optional($b->check_in)->format('M j') }}<span class="text-[#9ca3af]"> – </span>{{ optional($b->check_out)->format('M j, Y') }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="font-mono text-[15px] font-semibold tracking-[2px] text-[#1e1e1e]">{{ $b->passcode ?: '——————' }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $b->ttlockStatusBadge() }}">
                                        @if ($b->ttlock_status === 'active')<span class="size-[5px] rounded-full bg-[#22c55e]"></span>@endif
                                        {{ $b->ttlockStatusLabel() }}
                                    </span>
                                    @if ($b->ttlock_status === 'failed' && $b->ttlock_error)
                                        <div class="mt-1 max-w-[240px] truncate text-[11px] text-[#dc2626]" title="{{ $b->ttlock_error }}">{{ $b->ttlock_error }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if (in_array($b->ttlock_status, ['failed', 'deleted', 'disabled']) || ! $b->keyboard_pwd_id)
                                            <button wire:click="retryAccess({{ $b->id }})"
                                                    class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-medium text-[#374151] hover:bg-[#f9fafb]">Generate</button>
                                        @endif
                                        @if ($b->keyboard_pwd_id && $b->ttlock_status === 'active')
                                            <button wire:click="revokeAccess({{ $b->id }})"
                                                    class="rounded-lg border border-[#fee2e2] px-3 py-1.5 text-[12px] font-medium text-[#dc2626] hover:bg-[#fef2f2]">Revoke</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($passes as $b)
                    <div wire:key="gp-m-{{ $b->id }}" class="flex flex-col gap-2 px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-[#f38c00]">{{ $b->bookingCode() }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $b->ttlockStatusBadge() }}">
                                @if ($b->ttlock_status === 'active')<span class="size-[5px] rounded-full bg-[#22c55e]"></span>@endif
                                {{ $b->ttlockStatusLabel() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-medium text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                            <span class="font-mono text-[14px] font-semibold tracking-[2px] text-[#1e1e1e]">{{ $b->passcode ?: '——————' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $b->room_name ?: '—' }}</span>
                            <span>{{ optional($b->check_in)->format('M j') }} – {{ optional($b->check_out)->format('M j') }}</span>
                        </div>
                        @if ($b->ttlock_status === 'failed' && $b->ttlock_error)
                            <p class="text-[11px] text-[#dc2626]">{{ $b->ttlock_error }}</p>
                        @endif
                        <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                            @if (in_array($b->ttlock_status, ['failed', 'deleted', 'disabled']) || ! $b->keyboard_pwd_id)
                                <button wire:click="retryAccess({{ $b->id }})"
                                        class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-medium text-[#374151]">Generate</button>
                            @endif
                            @if ($b->keyboard_pwd_id && $b->ttlock_status === 'active')
                                <button wire:click="revokeAccess({{ $b->id }})"
                                        class="rounded-lg border border-[#fee2e2] px-3 py-1.5 text-[12px] font-medium text-[#dc2626]">Revoke</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">Showing {{ $passes->count() }} of {{ number_format($passes->total()) }} gate passes</p>
                @if ($passes->hasPages())
                    @php $last=$passes->lastPage();$cur=$passes->currentPage();$start=max(1,min($cur-1,$last-2));$end=min($last,$start+2); @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($passes->onFirstPage()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        @for ($pp = $start; $pp <= $end; $pp++)
                            <button type="button" wire:click="gotoPage({{ $pp }})" @class(['flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition','border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $pp === $cur,'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $pp !== $cur])>{{ $pp }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $passes->hasMorePages()) class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40"><svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
