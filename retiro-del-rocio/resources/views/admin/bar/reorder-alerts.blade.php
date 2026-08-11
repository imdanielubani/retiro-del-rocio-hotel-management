<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-7 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(20px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($items->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#dcfce7] text-[#16a34a]">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">Nothing needs restocking</p>
                <p class="text-[13px] text-[#6b7280]">Every item is above its minimum stock level.</p>
            </div>
        @else
            <table class="w-full min-w-[860px] border-collapse">
                <thead>
                    <tr class="bg-[#f9fafb] text-left">
                        @foreach (['Item', 'Current Stock', 'Minimum Level', 'Status', 'Suggested Reorder Qty'] as $col)
                            <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280] first:rounded-tl-2xl last:rounded-tr-2xl">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr wire:key="ra-{{ $item->id }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                            <td class="px-4 py-3.5">
                                <p class="text-[13px] font-semibold text-[#1e1e1e]">{{ $item->name }}</p>
                                <p class="text-[11px] text-[#9ca3af]">{{ $item->category ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->current_stock }}</td>
                            <td class="px-4 py-3.5 text-[13px] text-[#374151]">{{ $item->minimum_stock_level }}</td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badge = $item->isOutOfStock() ? ['bg' => '#fee2e2', 'fg' => '#dc2626'] : ['bg' => '#fef3c7', 'fg' => '#d97706'];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $badge['bg'] }}; color: {{ $badge['fg'] }};">{{ $item->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $item->reorderSuggestion() }} {{ $item->unitLabel() }}(s)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
