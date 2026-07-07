<div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white p-9 shadow-sm">
    {{-- Back to login --}}
    <a href="{{ route('admin.login') }}" wire:navigate
       class="inline-flex items-center gap-2 text-[13px] text-[#6b7280] transition hover:text-[#1e1e1e]">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 19l-7-7 7-7" />
        </svg>
        Back to login
    </a>

    <div class="mt-6 flex flex-col items-center gap-[21px]">
        {{-- Step badge --}}
        <div class="flex h-7 w-full items-center rounded-md bg-[#fff3e0] px-[10px]">
            <span class="text-[12px] text-[#995900]">Step 1 of 4&nbsp;&nbsp;&middot;&nbsp;&nbsp;Reset your password</span>
        </div>

        {{-- Heading --}}
        <div class="flex w-full flex-col gap-[10px]">
            <h1 class="text-[clamp(21px,1.9vw,26px)] font-bold leading-none text-[#1e1e1e]">Forgot password?</h1>
            <p class="text-[14px] leading-snug text-[#6b7280]">
                Enter the email address linked to your admin account. We'll send a 6-digit verification code.
            </p>
            <div class="h-px w-full bg-[#e5e7eb]"></div>
        </div>

        {{-- Mail icon --}}
        <div class="flex size-14 items-center justify-center rounded-full bg-[#fff3e0]">
            <svg class="size-7 text-[#f38c00]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2" />
                <path d="m22 7-10 6L2 7" />
            </svg>
        </div>

        @error('email')
            <div class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-600">
                {{ $message }}
            </div>
        @enderror

        <form wire:submit="sendCode" class="flex w-full flex-col gap-[21px]">
            {{-- Email --}}
            <input
                type="email"
                wire:model="email"
                autocomplete="username"
                autofocus
                placeholder="you@retirodelrocio.com"
                class="h-12 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-[19px] text-[13px] text-[#1e1e1e] placeholder:text-[#bfc7cc] focus:border-[#f38c00] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"
            >

            {{-- Submit --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="flex h-[52px] w-full items-center justify-center rounded-[10px] bg-[#f38c00] text-[16px] font-bold text-white transition hover:bg-[#dd7f00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/40 disabled:opacity-70"
            >
                <span wire:loading.remove wire:target="sendCode">Send Verification Code</span>
                <span wire:loading wire:target="sendCode">Sending…</span>
            </button>
        </form>

        {{-- Divider --}}
        <div class="h-px w-full bg-[#e5e7eb]"></div>

        {{-- Footer row --}}
        <div class="flex w-full items-center justify-between text-[12px]">
            <span class="text-[#6b7280]">Remembered your password?</span>
            <a href="{{ route('admin.login') }}" wire:navigate class="font-medium text-[#f38c00] hover:underline">
                Sign in instead &rarr;
            </a>
        </div>
    </div>
</div>
