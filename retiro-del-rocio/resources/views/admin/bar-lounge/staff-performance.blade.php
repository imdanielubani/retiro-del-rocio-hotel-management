<div class="flex flex-col gap-4">
    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Range filter --}}
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#e5e7eb] bg-white px-5 py-4">
        <p class="text-[12px] font-semibold text-[#1e1e1e]">Period</p>
        <select wire:model.live="range" class="h-[34px] rounded-lg border border-[#e5e7eb] bg-white px-2.5 text-[12px] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            <option value="today">Today</option>
            <option value="week">This week</option>
            <option value="month">This month</option>
            <option value="all">All time</option>
        </select>
        <p class="ml-auto text-[11px] text-[#6b7280]">Sales are credited to whichever bar staff member a settled tab is assigned to.</p>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        @if ($staff->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No bar staff yet</p>
                <p class="text-[13px] text-[#6b7280]">Create staff accounts with the Bar role in Access &amp; Users.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Staff','Status','Open Tabs','Tabs Settled','Revenue','Avg. Ticket'] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $row)
                            <tr class="border-b border-[#f3f4f6] last:border-0">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex size-8 items-center justify-center rounded-full bg-[#fff7ed] text-[11px] font-semibold text-[#f38c00]">{{ strtoupper(substr($row['name'], 0, 1)) }}</span>
                                        <span class="text-[13px] font-semibold text-[#1e1e1e]">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-[12px]" style="color: {{ $row['online'] ? '#16a34a' : '#9ca3af' }}">
                                        <span class="size-[7px] rounded-full" style="background: {{ $row['online'] ? '#22c55e' : '#d1d5db' }}"></span>
                                        {{ $row['online'] ? 'On shift now' : 'Offline' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[13px] text-[#1e1e1e]">{{ $row['open_tabs'] }}</td>
                                <td class="px-4 py-3 text-[13px] text-[#1e1e1e]">{{ $row['tabs_settled'] }}</td>
                                <td class="px-4 py-3 text-[13px] font-semibold text-[#1e1e1e]">{{ $row['revenue_label'] }}</td>
                                <td class="px-4 py-3 text-[13px] text-[#6b7280]">{{ $row['average_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
