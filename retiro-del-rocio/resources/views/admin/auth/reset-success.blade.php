<div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white p-9 shadow-sm">
    {{-- Back to email --}}
    <a href="{{ route('admin.password.request') }}" wire:navigate
       class="inline-flex items-center gap-2 text-[13px] text-[#6b7280] transition hover:text-[#1e1e1e]">
        <img src="{{ asset('images/arrow-narrow-left.png') }}" alt="" class="size-3.5">
        Back to email
    </a>

    <div class="mt-6 flex flex-col gap-[18px]">
        {{-- Step badge (success) --}}
        <div class="flex h-7 w-full items-center rounded-md bg-[#e7f6ec] px-[10px]">
            <span class="text-[12px] text-[#16a34a]">Step 4 of 4&nbsp;&nbsp;&middot;&nbsp;&nbsp;Verification successful</span>
        </div>

        {{-- Success check --}}
        <div class="flex w-full justify-center pt-2 pb-1">
            <div class="flex size-24 items-center justify-center rounded-full bg-[#e7f6ec]">
                <svg class="size-14 text-[#16a34a]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="m8.5 12 2.5 2.5 4.5-5" />
                </svg>
            </div>
        </div>

        {{-- Heading --}}
        <div class="flex w-full flex-col gap-[6px]">
            <h1 class="text-[clamp(21px,1.9vw,26px)] font-bold leading-none text-[#1e1e1e]">Email verified!</h1>
            <p class="text-[14px] leading-snug text-[#6b7280]">
                Your identity has been confirmed successfully. Your admin account is now fully verified and ready to access.
            </p>
            <div class="mt-2 h-px w-full bg-[#e5e7eb]"></div>
        </div>

        {{-- Verified account card --}}
        <div class="flex w-full items-center gap-3 rounded-xl bg-[#f9fafb] px-4 py-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#f38c00] text-[16px] font-bold text-white">
                {{ strtoupper(mb_substr($name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[14px] font-bold text-[#1e1e1e]">{{ $name }}</p>
                <p class="truncate text-[13px] text-[#6b7280]">{{ $email }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-[#e7f6ec] px-3 py-1 text-[12px] font-bold text-[#16a34a]">Verified</span>
        </div>

        {{-- Go to login --}}
        <a href="{{ route('admin.login') }}" wire:navigate
           class="flex h-[52px] w-full items-center justify-center rounded-[10px] bg-[#f38c00] text-[16px] font-bold text-white transition hover:bg-[#dd7f00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/40">
            Go to Login
        </a>
    </div>
</div>
