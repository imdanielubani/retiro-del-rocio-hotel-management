@php
    $badge = fn ($s) => match ($s) {
        'new' => 'bg-[#fff3e0] text-[#b45309]',
        'replied' => 'bg-[#e7f6ec] text-[#16a34a]',
        'archived' => 'bg-[#f3f4f6] text-[#6b7280]',
        default => 'bg-[#f3f4f6] text-[#6b7280]',
    };
@endphp

<div class="flex flex-col gap-4">
    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        {{-- Status tabs --}}
        <div class="flex flex-wrap gap-1.5">
            @foreach (['' => 'All', 'new' => 'New', 'replied' => 'Replied', 'archived' => 'Archived'] as $key => $label)
                <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                        @class([
                            'flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[13px] font-semibold transition',
                            'bg-[#1e2318] text-white' => $statusFilter === $key,
                            'bg-white text-[#374151] border border-[#e5e7eb] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                        ])>
                    {{ $label }}
                    <span class="rounded-full {{ $statusFilter === $key ? 'bg-white/20' : 'bg-[#f3f4f6]' }} px-1.5 text-[11px]">{{ $counts[$key ?: 'all'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Search --}}
        <div class="relative w-full lg:max-w-[320px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, email, message…"
                   class="h-11 w-full rounded-full border border-[#e5e7eb] bg-white pl-10 pr-4 text-[14px] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>
    </div>

    {{-- List --}}
    @if ($messages->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4z" fill="none"/><path d="M22 6 12 13 2 6" stroke-linecap="round" stroke-linejoin="round"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No messages {{ $statusFilter ? 'in this view' : 'yet' }}</p>
            <p class="text-[13px] text-[#6b7280]">Enquiries from the contact form will appear here.</p>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($messages as $m)
                <button type="button" wire:key="msg-{{ $m->id }}" wire:click="select({{ $m->id }})"
                        class="flex items-start gap-4 rounded-2xl border bg-white p-4 text-left transition hover:bg-[#f9fafb] {{ $m->status === 'new' ? 'border-[#f3d8b0]' : 'border-[#e5e7eb]' }}">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#f3f3ee] text-[13px] font-bold text-[#6b7280]">{{ $m->initials }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-[15px] font-bold text-[#1e1e1e]">{{ $m->full_name }}</p>
                            @if ($m->status === 'new')
                                <span class="size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                            @endif
                            <span class="ml-auto shrink-0 text-[12px] text-[#9ca3af]">{{ $m->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="truncate text-[13px] text-[#6b7280]">{{ $m->email }}{{ $m->phone ? ' · '.$m->phone : '' }}</p>
                        <p class="mt-1 line-clamp-2 text-[13px] text-[#374151]">{{ $m->message ?: 'No message provided.' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge($m->status) }}">{{ ucfirst($m->status) }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- ===== Detail + reply popup ===== --}}
    @if ($selected)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" wire:key="detail-{{ $selected->id }}"
             x-data x-on:keydown.escape.window="$wire.clearSelection()">
            <div class="absolute inset-0 bg-black/50" wire:click="clearSelection"></div>
            <div class="relative flex max-h-[88vh] w-full max-w-[560px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                 x-transition.scale.origin.center>
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-full bg-[#f3f3ee] text-[13px] font-bold text-[#6b7280]">{{ $selected->initials }}</div>
                        <div>
                            <p class="text-[15px] font-bold text-[#1e1e1e]">{{ $selected->full_name }}</p>
                            <p class="text-[12px] text-[#9ca3af]">{{ $selected->created_at->format('j M, Y g:i A') }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="clearSelection" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[12px] font-semibold {{ $badge($selected->status) }}">{{ ucfirst($selected->status) }}</span>
                    </div>

                    {{-- Contact details --}}
                    <dl class="mt-4 flex flex-col divide-y divide-[#f1f1ee] text-[14px]">
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="text-[#6b7280]">Email</dt>
                            <dd class="text-right"><a href="mailto:{{ $selected->email }}" class="font-medium text-[#ba6d04] hover:underline">{{ $selected->email }}</a></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 py-2.5">
                            <dt class="text-[#6b7280]">Phone</dt>
                            <dd class="text-right font-medium text-[#1e1e1e]">{{ $selected->phone ?: '—' }}</dd>
                        </div>
                    </dl>

                    {{-- Original message --}}
                    <div class="mt-4 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] p-4">
                        <p class="mb-1 text-[12px] uppercase tracking-wide text-[#9ca3af]">Message</p>
                        <p class="whitespace-pre-wrap text-[14px] leading-relaxed text-[#374151]">{{ $selected->message ?: 'No message provided.' }}</p>
                    </div>

                    {{-- Previous reply, if any --}}
                    @if ($selected->reply)
                        <div class="mt-4 rounded-xl border border-[#cdebd6] bg-[#f0fbf3] p-4">
                            <p class="mb-1 text-[12px] uppercase tracking-wide text-[#16a34a]">Your reply · {{ optional($selected->replied_at)->format('j M, Y g:i A') }}</p>
                            <p class="whitespace-pre-wrap text-[14px] leading-relaxed text-[#15803d]">{{ $selected->reply }}</p>
                        </div>
                    @endif
                </div>

                {{-- Reply composer --}}
                <div class="border-t border-[#e5e7eb] px-5 py-4">
                    <label class="mb-2 block text-[13px] font-semibold text-[#374151]">{{ $selected->reply ? 'Send another reply' : 'Reply to '.$selected->first_name }}</label>
                    <textarea wire:model="reply" rows="3" placeholder="Type your reply… it will be emailed to {{ $selected->email }}"
                              class="w-full resize-y rounded-xl border border-[#e5e7eb] p-3 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"></textarea>
                    @error('reply') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror

                    <div class="mt-3 flex items-center gap-2">
                        <button type="button" wire:click="sendReply" wire:loading.attr="disabled" wire:target="sendReply"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#f38c00] py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60">
                            <svg wire:loading wire:target="sendReply" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            <span wire:loading.remove wire:target="sendReply">Send reply</span>
                            <span wire:loading wire:target="sendReply">Sending…</span>
                        </button>
                        @if ($selected->status !== 'archived')
                            <button type="button" wire:click="archive({{ $selected->id }})"
                                    class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                                Archive
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
