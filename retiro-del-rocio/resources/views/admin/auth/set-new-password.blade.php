<div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white p-9 shadow-sm">
    {{-- Back to login --}}
    <a href="{{ route('admin.login') }}" wire:navigate
       class="inline-flex items-center gap-2 text-[13px] text-[#6b7280] transition hover:text-[#1e1e1e]">
        <img src="{{ asset('images/arrow-narrow-left.png') }}" alt="" class="size-3.5">
        Back to login
    </a>

    <div class="mt-6 flex flex-col gap-[18px]">
        {{-- Step badge --}}
        <div class="flex h-7 w-full items-center rounded-md bg-[#fff3e0] px-[10px]">
            <span class="text-[12px] text-[#995900]">Step 3 of 4&nbsp;&nbsp;&middot;&nbsp;&nbsp;Create new password</span>
        </div>

        {{-- Heading --}}
        <div class="flex w-full flex-col gap-[6px]">
            <h1 class="text-[clamp(21px,1.9vw,26px)] font-bold leading-none text-[#1e1e1e]">Enter verification Code</h1>
            <p class="text-[14px] leading-snug text-[#6b7280]">
                Your identity has been verified. Create a strong new password for your admin account.
            </p>
            <div class="mt-2 h-px w-full bg-[#e5e7eb]"></div>
        </div>

        <form wire:submit="resetPassword"
              x-data="{
                  pw: '',
                  pwc: '',
                  showPw: false,
                  showPwc: false,
                  get hasLen() { return this.pw.length >= 12; },
                  get hasUpper() { return /[A-Z]/.test(this.pw); },
                  get hasNumber() { return /[0-9]/.test(this.pw); },
                  get hasSpecial() { return /[^A-Za-z0-9]/.test(this.pw); },
                  get score() { return [this.hasLen, this.hasUpper, this.hasNumber, this.hasSpecial].filter(Boolean).length; },
                  get level() { return this.pw === '' ? 0 : (this.score <= 1 ? 1 : (this.score === 2 ? 2 : 3)); },
                  get label() { return this.level === 0 ? '' : (this.level === 1 ? 'Weak' : (this.level === 2 ? 'Medium' : 'Strong')); },
                  get color() { return this.level === 3 ? '#16a34a' : (this.level === 2 ? '#f59e0b' : '#ef4444'); },
                  syncPw() { $wire.set('password', this.pw, false); },
                  syncPwc() { $wire.set('password_confirmation', this.pwc, false); },
              }"
              class="flex w-full flex-col gap-[18px]">

            {{-- New password --}}
            <div class="flex w-full flex-col gap-[8px]">
                <label class="text-[12px] text-[#6b7280]">New password</label>
                <div class="relative flex h-12 items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-4 focus-within:border-[#f38c00] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#f38c00]/20">
                    <input
                        :type="showPw ? 'text' : 'password'"
                        x-model="pw"
                        @input="syncPw()"
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                        class="h-full w-full bg-transparent pr-12 text-[13px] text-[#1e1e1e] placeholder:text-[#6b7280] focus:outline-none"
                    >
                    <button type="button" @click="showPw = !showPw"
                            class="absolute right-4 text-[12px] font-medium text-[#f38c00] hover:underline"
                            x-text="showPw ? 'Hide' : 'Show'">Show</button>
                </div>
            </div>

            {{-- Confirm new password --}}
            <div class="flex w-full flex-col gap-[8px]">
                <label class="text-[12px] text-[#6b7280]">Confirm new password</label>
                <div class="relative flex h-12 items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-4 focus-within:border-[#f38c00] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#f38c00]/20">
                    <input
                        :type="showPwc ? 'text' : 'password'"
                        x-model="pwc"
                        @input="syncPwc()"
                        autocomplete="new-password"
                        placeholder="••••••••••••"
                        class="h-full w-full bg-transparent pr-12 text-[13px] text-[#1e1e1e] placeholder:text-[#6b7280] focus:outline-none"
                    >
                    <button type="button" @click="showPwc = !showPwc"
                            class="absolute right-4 text-[12px] font-medium text-[#f38c00] hover:underline"
                            x-text="showPwc ? 'Hide' : 'Show'">Show</button>
                </div>
            </div>

            @error('password')
                <p class="text-[12px] text-red-600">{{ $message }}</p>
            @enderror

            {{-- Strength meter --}}
            <div class="flex w-full flex-col gap-[8px]">
                <div class="flex items-center justify-between text-[12px]">
                    <span class="text-[#6b7280]">Password strength</span>
                    <span class="font-medium" x-text="label" :style="'color:' + color"></span>
                </div>
                <div class="flex w-full gap-1.5">
                    <template x-for="i in 3" :key="i">
                        <div class="h-1.5 flex-1 rounded-full"
                             :style="(i <= level) ? ('background:' + color) : 'background:#e5e7eb'"></div>
                    </template>
                </div>
            </div>

            {{-- Checklist --}}
            <ul class="flex w-full flex-col gap-[10px] text-[13px]">
                <li class="flex items-center gap-2">
                    <template x-if="hasLen">
                        <svg class="size-4 text-[#16a34a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <template x-if="!hasLen">
                        <svg class="size-4 text-[#cbd5e1]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <span :class="hasLen ? 'text-[#374151]' : 'text-[#9ca3af]'">Minimum 12 characters</span>
                </li>
                <li class="flex items-center gap-2">
                    <template x-if="hasUpper">
                        <svg class="size-4 text-[#16a34a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <template x-if="!hasUpper">
                        <svg class="size-4 text-[#cbd5e1]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <span :class="hasUpper ? 'text-[#374151]' : 'text-[#9ca3af]'">At least one uppercase letter</span>
                </li>
                <li class="flex items-center gap-2">
                    <template x-if="hasNumber">
                        <svg class="size-4 text-[#16a34a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <template x-if="!hasNumber">
                        <svg class="size-4 text-[#cbd5e1]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <span :class="hasNumber ? 'text-[#374151]' : 'text-[#9ca3af]'">At least one number</span>
                </li>
                <li class="flex items-center gap-2">
                    <template x-if="hasSpecial">
                        <svg class="size-4 text-[#16a34a]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <template x-if="!hasSpecial">
                        <svg class="size-4 text-[#cbd5e1]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                    </template>
                    <span :class="hasSpecial ? 'text-[#374151]' : 'text-[#9ca3af]'">At least one special character (!@#$)</span>
                </li>
            </ul>

            {{-- Submit --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="mt-1 flex h-[52px] w-full items-center justify-center rounded-[10px] bg-[#f38c00] text-[16px] font-bold text-white transition hover:bg-[#dd7f00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/40 disabled:opacity-70"
            >
                <span wire:loading.remove wire:target="resetPassword">Verify Code</span>
                <span wire:loading wire:target="resetPassword">Saving…</span>
            </button>
        </form>
    </div>
</div>
