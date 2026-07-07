<div class="rounded-2xl border border-[#e5e7eb] bg-white p-10 shadow-sm">
    <h1 class="text-[clamp(22px,2vw,28px)] font-bold leading-tight text-[#1e1e1e]">Welcome back</h1>
    <p class="mt-2 text-sm text-[#6b7280]">Sign in to your admin account to continue</p>
    <span class="mt-4 block h-[3px] w-14 rounded-full bg-[#f38c00]"></span>

    @if ($errors->has('email'))
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
            {{ $errors->first('email') }}
        </div>
    @endif

    <form wire:submit="login" class="mt-6 space-y-5">
        {{-- Email --}}
        <div>
            <label for="email" class="mb-2 block text-xs text-[#6b7280]">Email address</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="username"
                autofocus
                placeholder="admin@retirodelrocio.com"
                class="h-12 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-4 text-sm text-[#1e1e1e] placeholder:text-[#b2bac2] focus:border-[#f38c00] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"
            >
        </div>

        {{-- Password --}}
        <div x-data="{ show: false }">
            <label for="password" class="mb-2 block text-xs text-[#6b7280]">Password</label>
            <div class="relative flex h-12 items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-4 focus-within:border-[#f38c00] focus-within:bg-white focus-within:ring-2 focus-within:ring-[#f38c00]/20">
                <input
                    :type="show ? 'text' : 'password'"
                    id="password"
                    wire:model="password"
                    autocomplete="current-password"
                    placeholder="••••••••••••"
                    class="h-full w-full bg-transparent pr-12 text-sm text-[#1e1e1e] placeholder:text-[#6b7280] focus:outline-none"
                >
                <button
                    type="button"
                    @click="show = !show"
                    class="absolute right-4 text-xs font-medium text-[#f38c00] hover:underline"
                    x-text="show ? 'Hide' : 'Show'"
                >Show</button>
            </div>
        </div>

        {{-- Remember + forgot --}}
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-xs text-[#6b7280]">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="size-4 rounded border-[#e5e7eb] bg-[#f9fafb] text-[#f38c00] focus:ring-[#f38c00]/30"
                >
                Keep me signed in
            </label>

            <a href="{{ route('admin.password.request') }}" wire:navigate
               class="text-xs font-medium text-[#f38c00] hover:underline">
                Forgot password?
            </a>
        </div>

        {{-- Submit --}}
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="flex h-[52px] w-full items-center justify-center rounded-[10px] bg-[#f38c00] text-base font-bold text-white transition hover:bg-[#dd7f00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/40 disabled:opacity-70"
        >
            <span wire:loading.remove wire:target="login">Sign In</span>
            <span wire:loading wire:target="login">Signing in…</span>
        </button>
    </form>
</div>
