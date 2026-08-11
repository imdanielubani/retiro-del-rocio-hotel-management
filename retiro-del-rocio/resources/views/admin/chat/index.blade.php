<div
    wire:poll.5s
    x-data="{}"
    x-on:chat-message-received.window="
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const now = ctx.currentTime;
            const tone = (freq, start, dur) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0, now + start);
                gain.gain.linearRampToValueAtTime(0.2, now + start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, now + start + dur);
                osc.connect(gain).connect(ctx.destination);
                osc.start(now + start);
                osc.stop(now + start + dur);
            };
            tone(880, 0, 0.18);
            tone(1318.5, 0.15, 0.22);
        } catch (e) {}
    "
    class="flex items-start gap-4"
>
    {{-- ===== Channel list ===== --}}
    <div class="flex h-[500px] w-[300px] shrink-0 flex-col gap-2 overflow-y-auto rounded-2xl border border-[#e5e7eb] bg-white p-3">
        @foreach ($channels as $channel)
            <button type="button" wire:click="selectRole('{{ $channel['role'] }}')"
                    @class([
                        'flex items-start gap-3 rounded-xl border p-3 text-left transition',
                        'border-[#f38c00] bg-[#fff7ed]' => $selectedRole === $channel['role'],
                        'border-transparent hover:bg-[#f9fafb]' => $selectedRole !== $channel['role'],
                    ])>
                <span class="relative flex size-10 shrink-0 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    @switch($channel['role'])
                        @case('housekeeping')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 11h4a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h4"/><path d="M9 11V9a3 3 0 0 1 6 0v2"/></svg>
                            @break
                        @case('maintenance')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2-2z"/></svg>
                            @break
                        @case('security')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6z"/></svg>
                            @break
                        @default
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21v-1a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v1"/><circle cx="10" cy="7" r="3"/><path d="M16 3.5a3 3 0 0 1 0 6M19 21v-1a4.5 4.5 0 0 0-2.5-4"/></svg>
                    @endswitch
                    <span @class([
                        'absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-white',
                        'bg-[#22c55e]' => $channel['online'],
                        'bg-[#d1d5db]' => ! $channel['online'],
                    ])></span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $channel['label'] }}</span>
                        @if ($channel['last_message_label'])
                            <span class="shrink-0 text-[10px] text-[#9ca3af]">{{ $channel['last_message_label'] }}</span>
                        @endif
                    </span>
                    <span class="mt-0.5 block text-[11px]" style="color: {{ $channel['online'] ? '#16a34a' : '#9ca3af' }}">{{ $channel['online'] ? 'Online' : 'Offline' }}</span>
                    <span class="mt-1 flex items-center justify-between gap-2">
                        <span class="truncate text-[11px] text-[#6b7280]">{{ $channel['last_message'] ?: 'No messages yet' }}</span>
                        @if ($channel['unread_count'] > 0)
                            <span class="flex size-[18px] shrink-0 items-center justify-center rounded-full bg-[#f38c00] text-[10px] font-bold text-white">{{ $channel['unread_count'] > 9 ? '9+' : $channel['unread_count'] }}</span>
                        @endif
                    </span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ===== Thread ===== --}}
    <div class="flex h-[820px] flex-1 flex-col overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
        @if (! $selectedRole)
            <div class="flex flex-1 flex-col items-center justify-center gap-2 text-center">
                <svg class="size-8 text-[#d1d5db]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <p class="text-[13px] text-[#6b7280]">Select a station to start messaging.</p>
            </div>
        @else
            @php($channel = collect($channels)->firstWhere('role', $selectedRole))
            {{-- Header --}}
            <div class="flex items-center gap-3 border-b border-[#e5e7eb] px-5 py-4">
                <span class="flex size-10 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21v-1a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v1"/><circle cx="10" cy="7" r="3"/></svg>
                </span>
                <div>
                    <p class="text-[15px] font-semibold text-[#1e1e1e]">{{ ucfirst($selectedRole) }}</p>
                    <p class="text-[11px]" style="color: {{ $channel && $channel['online'] ? '#16a34a' : '#9ca3af' }}">{{ $channel && $channel['online'] ? 'Online' : 'Offline' }}</p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                @forelse ($messages as $message)
                    <div class="flex {{ $message->sender_role === 'admin' ? 'justify-end' : 'justify-start' }}">
                        <div @class([
                            'max-w-[70%] rounded-2xl px-4 py-2.5',
                            'bg-[#f38c00] text-white' => $message->sender_role === 'admin',
                            'bg-[#f9fafb] text-[#1e1e1e]' => $message->sender_role !== 'admin',
                        ])>
                            <p class="text-[13px] leading-relaxed">{{ $message->body }}</p>
                            <p @class([
                                'mt-1 text-[10px]',
                                'text-white/70' => $message->sender_role === 'admin',
                                'text-[#9ca3af]' => $message->sender_role !== 'admin',
                            ])>{{ optional($message->created_at)->format('g:i A') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-[13px] text-[#9ca3af]">No messages yet — say hello.</p>
                @endforelse
            </div>

            {{-- Composer --}}
            <form wire:submit.prevent="send" class="flex items-end gap-2 border-t border-[#e5e7eb] p-4">
                <textarea wire:model="body" rows="1" placeholder="Message {{ ucfirst($selectedRole) }}..."
                          class="h-11 flex-1 resize-none rounded-full border border-[#e5e7eb] bg-[#f9fafb] px-4 py-2.5 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"></textarea>
                <button type="submit" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#f38c00] text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><path d="M2.94 2.94a1.5 1.5 0 0 1 1.61-.34l17 6.5a1.5 1.5 0 0 1 0 2.8l-17 6.5a1.5 1.5 0 0 1-2-1.83L4.5 12 2.55 4.77a1.5 1.5 0 0 1 .39-1.83z"/></svg>
                </button>
            </form>
            @error('body')
                <p class="px-4 pb-3 text-[11px] text-[#dc2626]">{{ $message }}</p>
            @enderror
        @endif
    </div>
</div>
