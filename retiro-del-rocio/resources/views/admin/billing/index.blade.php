<div class="flex flex-col gap-4" wire:poll.15s>
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5"
                 style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Bills table ===== --}}
    <div class="overflow-hidden rounded-xl border border-[#e5e7eb] bg-white">
        <div class="flex flex-col gap-3 border-b border-[#e5e7eb] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[15px] font-medium text-[#1e1e1e]">Checked-In Guests</p>
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-3.5 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search guest or room..."
                       class="h-9 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-3 text-[13px] text-[#1e1e1e] placeholder:text-[#1e1e1e]/50 focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f3f3ee]">
                    <svg class="size-6 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20" stroke-linecap="round"/></svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">No checked-in guests found</p>
                <p class="text-[13px] text-[#6b7280]">Guests currently in-house will appear here with their bill.</p>
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[720px] border-collapse">
                    <thead>
                        <tr class="bg-[#f9fafb] text-left">
                            @foreach (['Guest', 'Room', 'Checkout', 'Outstanding', 'Status', ''] as $col)
                                <th class="border-b border-[#e5e7eb] px-4 py-2.5 text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="bill-{{ $row['booking_id'] }}" class="border-b border-[#e5e7eb] {{ $loop->even ? 'bg-[#f9fafb]' : 'bg-white' }}">
                                <td class="px-4 py-3.5 text-[13px] font-medium text-[#1e1e1e]">{{ $row['guest_name'] ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] text-[#6b7280]">{{ $row['room_label'] }}</td>
                                <td class="px-4 py-3.5 text-[12px] text-[#6b7280]">{{ $row['check_out_label'] ?: '—' }}</td>
                                <td class="px-4 py-3.5 text-[13px] font-bold {{ $row['has_balance'] ? 'text-[#f38c00]' : 'text-[#9ca3af]' }}">{{ $row['total_due_label'] }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($row['has_balance'])
                                        <span class="inline-flex items-center rounded-full bg-[#fef3c7] px-2.5 py-1 text-[11px] font-medium text-[#d97706]">Outstanding</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-1 text-[11px] font-medium text-[#16a34a]">Settled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <button type="button" wire:click="viewBill({{ $row['booking_id'] }})"
                                            class="rounded-lg border border-[#e5e7eb] px-3 py-1.5 text-[12px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                                        View Bill
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="flex flex-col divide-y divide-[#e5e7eb] md:hidden">
                @foreach ($rows as $row)
                    <button type="button" wire:click="viewBill({{ $row['booking_id'] }})" wire:key="bill-m-{{ $row['booking_id'] }}"
                            class="flex flex-col gap-2 px-4 py-3.5 text-left">
                        <div class="flex items-center justify-between">
                            <p class="text-[14px] font-medium text-[#1e1e1e]">{{ $row['guest_name'] ?: '—' }}</p>
                            <p class="text-[14px] font-bold {{ $row['has_balance'] ? 'text-[#f38c00]' : 'text-[#9ca3af]' }}">{{ $row['total_due_label'] }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[12px] text-[#6b7280]">
                            <span>{{ $row['room_label'] }} · out {{ $row['check_out_label'] ?: '—' }}</span>
                            @if ($row['has_balance'])
                                <span class="inline-flex items-center rounded-full bg-[#fef3c7] px-2 py-0.5 text-[10px] font-medium text-[#d97706]">Outstanding</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-[#dcfce7] px-2 py-0.5 text-[10px] font-medium text-[#16a34a]">Settled</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @include('admin.billing.partials.bill-detail')
</div>
