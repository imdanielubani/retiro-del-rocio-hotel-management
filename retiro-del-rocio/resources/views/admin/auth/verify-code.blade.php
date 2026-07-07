<div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white p-9 shadow-sm">
    {{-- Back to email --}}
    <a href="{{ route('admin.password.request') }}" wire:navigate
       class="inline-flex items-center gap-2 text-[13px] text-[#6b7280] transition hover:text-[#1e1e1e]">
        <img src="{{ asset('images/arrow-narrow-left.png') }}" alt="" class="size-3.5">
        Back to email
    </a>

    <div class="mt-6 flex flex-col gap-[21px]">
        {{-- Step badge --}}
        <div class="flex h-7 w-full items-center rounded-md bg-[#fff3e0] px-[10px]">
            <span class="text-[12px] text-[#995900]">Step 2 of 4&nbsp;&nbsp;&middot;&nbsp;&nbsp;Check your email</span>
        </div>

        {{-- Heading --}}
        <div class="flex w-full flex-col gap-[8px]">
            <h1 class="text-[clamp(21px,1.9vw,26px)] font-bold leading-none text-[#1e1e1e]">Enter verification Code</h1>
            <p class="text-[14px] leading-snug text-[#6b7280]">
                We sent a 6-digit code to<br>
                <span class="font-bold text-[#f38c00]">{{ $email }}</span><br>
                Check your inbox and enter the code below.
            </p>
            <div class="mt-2 h-px w-full bg-[#e5e7eb]"></div>
        </div>

        <form wire:submit="verify"
              x-data="{
                  digits: ['', '', '', '', '', ''],
                  secondsLeft: @js($expiresInSeconds),
                  timer: null,
                  get code() { return this.digits.join(''); },
                  get countdown() {
                      const m = Math.floor(this.secondsLeft / 60).toString().padStart(2, '0');
                      const s = (this.secondsLeft % 60).toString().padStart(2, '0');
                      return m + ':' + s;
                  },
                  init() {
                      this.timer = setInterval(() => { if (this.secondsLeft > 0) this.secondsLeft--; }, 1000);
                  },
                  boxes() { return [...this.$root.querySelectorAll('input[data-otp]')]; },
                  sync() { $wire.set('code', this.code, false); },
                  onInput(i, e) {
                      const v = e.target.value.replace(/\D/g, '');
                      this.digits[i] = v.slice(-1) || '';
                      this.sync();
                      if (this.digits[i] && i < 5) this.boxes()[i + 1]?.focus();
                  },
                  onKeydown(i, e) {
                      if (e.key === 'Backspace' && !this.digits[i] && i > 0) this.boxes()[i - 1]?.focus();
                  },
                  onPaste(e) {
                      e.preventDefault();
                      const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6).split('');
                      for (let i = 0; i < 6; i++) this.digits[i] = t[i] || '';
                      this.sync();
                      const last = Math.min(t.length, 6) - 1;
                      if (last >= 0) this.boxes()[last]?.focus();
                  },
              }"
              @code-resent.window="digits = ['', '', '', '', '', '']; secondsLeft = @js($ttl); sync(); $nextTick(() => boxes()[0]?.focus())"
              class="flex w-full flex-col gap-[21px]">

            {{-- Verification code --}}
            <div class="flex w-full flex-col gap-[10px]">
                <label class="text-[12px] text-[#6b7280]">Verification code</label>
                <div class="flex w-full justify-between">
                    <template x-for="(d, i) in digits" :key="i">
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            data-otp
                            autocomplete="one-time-code"
                            placeholder="–"
                            :value="digits[i]"
                            @input="onInput(i, $event)"
                            @keydown="onKeydown(i, $event)"
                            @paste="onPaste($event)"
                            @focus="$event.target.select()"
                            :class="digits[i]
                                ? 'border-[#f38c00] bg-white text-[#f38c00]'
                                : 'border-[#e5e7eb] bg-[#f9fafb] text-[#9ca3af]'"
                            class="size-14 rounded-xl border text-center text-2xl font-bold transition placeholder:text-[#cbd5e1] focus:border-[#f38c00] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"
                        >
                    </template>
                </div>
            </div>

            @error('code')
                <div class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-600">
                    {{ $message }}
                </div>
            @enderror

            {{-- Expiry + resend --}}
            <div class="flex w-full items-center rounded-lg bg-[#f9fafb] px-4 py-3 text-[12px]">
                <div class="flex items-center gap-2 text-[#6b7280]">
                    <img src="{{ asset('images/alarm-01.png') }}" alt="" class="size-4">
                    <span>Code expires in</span>
                </div>
                <span class="ml-auto font-bold text-[#1e1e1e]" x-text="countdown">--:--</span>
                <button type="button" wire:click="resend"
                        class="ml-auto font-medium text-[#f38c00] hover:underline">
                    Resend code
                </button>
            </div>

            {{-- Verify --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="flex h-[52px] w-full items-center justify-center rounded-[10px] bg-[#f38c00] text-[16px] font-bold text-white transition hover:bg-[#dd7f00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/40 disabled:opacity-70"
            >
                <span wire:loading.remove wire:target="verify">Verify Code</span>
                <span wire:loading wire:target="verify">Verifying…</span>
            </button>
        </form>

        {{-- Spam hint --}}
        <p class="w-full text-center text-[12px] text-[#9ca3af]">
            Didn't receive the email? Check your spam folder.
        </p>
    </div>
</div>
