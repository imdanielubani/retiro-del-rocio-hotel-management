{{--
    Shown in place of the form once a password/PIN was just set — neither
    can be read back once hashed, so this is the only chance to hand it to
    whoever's relaying it to the staffer. Expects $savedCredentials
    (['name' => ..., 'password' => ?string, 'pin' => ?string]).
--}}
<div class="px-6 py-6">
    <div class="flex flex-col items-center gap-2 pb-5 text-center">
        <div class="flex size-12 items-center justify-center rounded-full bg-[#dcfce7] text-[#16a34a]">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h4 class="text-[16px] font-bold text-[#1e1e1e]">Credentials updated</h4>
        <p class="text-[13px] text-[#6b7280]">Share these with <span class="font-semibold text-[#374151]">{{ $savedCredentials['name'] }}</span> — they won't be shown again.</p>
    </div>
    <div class="flex flex-col gap-3">
        @if ($savedCredentials['password'])
            <div class="flex items-center justify-between rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.5px] text-[#9ca3af]">New password</p>
                    <p class="font-mono text-[16px] font-bold text-[#1e1e1e]">{{ $savedCredentials['password'] }}</p>
                </div>
            </div>
        @endif
        @if ($savedCredentials['pin'])
            <div class="flex items-center justify-between rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.5px] text-[#9ca3af]">New PIN</p>
                    <p class="font-mono text-[16px] font-bold text-[#1e1e1e]">{{ $savedCredentials['pin'] }}</p>
                </div>
            </div>
        @endif
    </div>
    <button type="button" wire:click="closeModals" class="mt-6 w-full rounded-xl bg-[#f38c00] py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Done</button>
</div>
