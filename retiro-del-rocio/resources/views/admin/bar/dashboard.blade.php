<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-7 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(18px,2vw,26px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Recent stock movements ===== --}}
    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-3.5">
            <p class="text-[14px] font-semibold text-[#1e1e1e]">Recent Stock Movements</p>
        </div>
        @if ($recentMovements->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#fff7ed] text-[#f38c00]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M8 17V9M13 17V5M18 17v-6"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No stock movements yet</p>
                <p class="text-[13px] text-[#6b7280]">Stock in, stock out, and adjustments will show up here.</p>
            </div>
        @else
            <table class="w-full min-w-[760px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Type', 'Qty', 'Reason', 'When'] as $col)
                            <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentMovements as $m)
                        <tr wire:key="rm-{{ $m->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $m->item?->name ?: '—' }}</td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badge = str_contains($m->type, 'in') || $m->type === 'adjustment_increase'
                                        ? ['bg' => '#dcfce7', 'fg' => '#16a34a']
                                        : ['bg' => '#fee2e2', 'fg' => '#dc2626'];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $badge['bg'] }}; color: {{ $badge['fg'] }};">{{ $m->typeLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->quantity }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $m->reasonLabel() }}</td>
                            <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ optional($m->occurred_at)->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
