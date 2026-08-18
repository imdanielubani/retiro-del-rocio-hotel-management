<div class="flex flex-col gap-4">

    {{-- ===== Filters ===== --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#e5e7eb] bg-white px-4 py-3">
        <select wire:model.live="typeFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="">All types</option>
            @foreach ($types as $type => $meta)
                <option value="{{ $type }}">{{ $meta['label'] }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="rounded-lg border border-[#e5e7eb] px-3 py-2 text-[13px]">
            <option value="">All statuses</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
        </select>
        @if ($unreadCount > 0)
            <span class="rounded-full bg-[#fff3e0] px-2.5 py-1 text-[12px] font-semibold text-[#b45309]">{{ $unreadCount }} unread</span>
        @endif
        <button type="button" wire:click="markAllRead" @disabled($unreadCount === 0)
                class="ml-auto rounded-lg border border-[#e5e7eb] bg-white px-4 py-2 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb] disabled:opacity-40">
            Mark all as read
        </button>
    </div>

    {{-- ===== Notifications ===== --}}
    @if ($notifications->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </div>
            <p class="text-[15px] font-semibold text-[#1e1e1e]">You're all caught up</p>
            <p class="text-[13px] text-[#6b7280]">{{ $typeFilter || $statusFilter ? 'Try a different filter.' : 'New bookings, messages and payments will show here.' }}</p>
        </div>
    @else
        {{-- List + pagination (one card, matches Bookings) --}}
        <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
            <div class="flex flex-col divide-y divide-[#f1f1ee]">
                @foreach ($notifications as $n)
                    @php $meta = $this->meta($n->type); $d = $n->data ?? []; @endphp
                    <div wire:key="notif-{{ $n->id }}" class="flex items-start gap-3 px-5 py-3.5 {{ $n->read_at ? '' : 'bg-[#fffaf3]' }}">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full" style="background: {{ $meta['bg'] }}; color: {{ $meta['fg'] }}">
                            @switch($meta['icon'])
                                @case('message')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    @break
                                @case('check')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    @break
                                @case('spa')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 0-7 7c0 3 2 5 2 8h10c0-3 2-5 2-8a7 7 0 0 0-7-7z"/><path d="M9 22h6"/></svg>
                                    @break
                                @case('gym')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 6.5 17.5 17.5M4 9l-1 1 2 2M20 15l1-1-2-2M7 4 4 7l3 3M17 20l3-3-3-3"/></svg>
                                    @break
                                @case('restaurant')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7a3 3 0 0 0 3 3v10M6 2v6M9 2v6M18 2c-1.5 1-2.5 3-2.5 6.5 0 2 1 3 2.5 3.5v10"/></svg>
                                    @break
                                @case('cinema')
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/></svg>
                                    @break
                                @default
                                    <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            @endswitch
                        </div>

                        {{-- Plain link (not wire:navigate + wire:click combined — unreliable together);
                             marking read is a deliberate action via the three-dot menu below. --}}
                        <a href="{{ $this->url($n) }}" wire:navigate class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $d['customer'] ?? $d['name'] ?? $meta['label'] }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" style="background: {{ $meta['bg'] }}; color: {{ $meta['fg'] }}">{{ $meta['label'] }}</span>
                            </div>
                            <p class="truncate text-[12px] text-[#6b7280]">{{ $d['message'] ?? $d['preview'] ?? $d['room'] ?? $d['service'] ?? $d['plan'] ?? $d['area'] ?? $d['movie'] ?? '—' }}</p>
                            <p class="mt-0.5 text-[11px] text-[#9ca3af]">{{ $n->created_at->diffForHumans() }}</p>
                        </a>

                        @unless ($n->read_at)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-[#f38c00]"></span>
                        @endunless

                        @include('admin.notifications.partials.notification-actions', ['n' => $n])
                    </div>
                @endforeach
            </div>

            {{-- Footer / pagination (inside the same card, matches Bookings) --}}
            <div class="flex flex-col gap-3 border-t border-[#e5e7eb] px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#6b7280]">
                    Showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ number_format($notifications->total()) }} notifications
                </p>
                @if ($notifications->hasPages())
                    @php
                        $last = $notifications->lastPage();
                        $cur = $notifications->currentPage();
                        $start = max(1, min($cur - 1, $last - 2));
                        $end = min($last, $start + 2);
                    @endphp
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($notifications->onFirstPage())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        @for ($p = $start; $p <= $end; $p++)
                            <button type="button" wire:click="gotoPage({{ $p }})"
                                    @class([
                                        'flex size-8 items-center justify-center rounded-md border text-[12px] font-medium transition',
                                        'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $p === $cur,
                                        'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $p !== $cur,
                                    ])>{{ $p }}</button>
                        @endfor
                        <button type="button" wire:click="nextPage" @disabled(! $notifications->hasMorePages())
                                class="flex size-8 items-center justify-center rounded-md border border-[#e5e7eb] bg-white text-[#6b7280] transition hover:bg-[#f9fafb] disabled:opacity-40">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
