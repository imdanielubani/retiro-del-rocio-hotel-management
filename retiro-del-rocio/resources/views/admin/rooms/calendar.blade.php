@php
    $today = now();
    $isThisMonth = $monthStart->isSameMonth($today);
    $todayLeft = $isThisMonth ? round(($today->day - 1) / $daysInMonth * 100, 4) : null;
@endphp

<div class="flex flex-col gap-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.rooms.edit', $room) }}" wire:navigate
           class="flex items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to room
        </a>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="prevMonth" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] transition hover:bg-[#f9fafb]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <p class="min-w-[140px] text-center text-[16px] font-bold text-[#1e1e1e]">{{ $title }}</p>
            <button type="button" wire:click="nextMonth" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] transition hover:bg-[#f9fafb]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
        @foreach (['Confirmed' => '#16a34a', 'Checked In' => '#7c3aed', 'Pending' => '#d97706'] as $label => $color)
            <span class="flex items-center gap-1.5 text-[12px] text-[#6b7280]"><span class="size-2.5 rounded-full" style="background: {{ $color }}"></span>{{ $label }}</span>
        @endforeach
    </div>

    @if ($unitsCount === 0)
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No room numbers yet</p>
            <p class="text-[13px] text-[#6b7280]">Add room numbers on the room's edit page to see their booking calendar.</p>
            <a href="{{ route('admin.rooms.edit', $room) }}" wire:navigate class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add room numbers</a>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-[#e5e7eb] bg-white">
            <div class="overflow-x-auto">
                <div class="min-w-[820px]">
                    {{-- Day header --}}
                    <div class="flex border-b border-[#e5e7eb] bg-[#f9fafb]">
                        <div class="w-[110px] shrink-0 border-r border-[#e5e7eb] px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#6b7280]">Room #</div>
                        <div class="relative flex flex-1">
                            @foreach ($days as $d)
                                <div class="flex-1 border-r border-[#f1f1ee] py-2 text-center text-[10px] text-[#9ca3af] last:border-r-0">{{ $d }}</div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Unit rows --}}
                    @foreach ($rows as $row)
                        <div class="flex border-b border-[#f1f1ee] last:border-b-0">
                            <div class="flex w-[110px] shrink-0 items-center border-r border-[#e5e7eb] px-3 py-2">
                                <span class="text-[13px] font-bold text-[#1e1e1e]">{{ $row['unit']->number }}</span>
                            </div>
                            <div class="relative h-12 flex-1">
                                {{-- day gridlines --}}
                                <div class="absolute inset-0 flex">
                                    @foreach ($days as $d)
                                        <div class="flex-1 border-r border-[#f5f5f2] last:border-r-0"></div>
                                    @endforeach
                                </div>
                                {{-- today marker --}}
                                @if ($todayLeft !== null)
                                    <div class="absolute top-0 bottom-0 z-10 w-px bg-[#f38c00]/60" style="left: {{ $todayLeft }}%"></div>
                                @endif
                                {{-- booking bars --}}
                                @foreach ($row['bars'] as $bar)
                                    @php $b = $bar['booking']; @endphp
                                    <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate
                                       class="absolute top-1.5 bottom-1.5 z-20 flex items-center overflow-hidden rounded-md px-2 text-[11px] font-medium text-white"
                                       style="left: {{ $bar['left'] }}%; width: calc({{ $bar['width'] }}% - 2px); background: {{ $b->statusColor() }}"
                                       title="{{ $b->bookingCode() }} · {{ $b->customer_name }} · {{ optional($b->check_in)->format('M j') }} → {{ optional($b->check_out)->format('M j') }}">
                                        <span class="truncate">{{ $b->customer_name ?: $b->bookingCode() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- Overflow (bookings that exceed the number of rooms) --}}
                    @if (! empty($overflow['bars']))
                        <div class="flex border-t-2 border-[#fecaca]">
                            <div class="flex w-[110px] shrink-0 items-center border-r border-[#e5e7eb] px-3 py-2">
                                <span class="text-[12px] font-semibold text-[#dc2626]">Overbooked</span>
                            </div>
                            <div class="relative h-12 flex-1">
                                <div class="absolute inset-0 flex">
                                    @foreach ($days as $d)<div class="flex-1 border-r border-[#f5f5f2] last:border-r-0"></div>@endforeach
                                </div>
                                @foreach ($overflow['bars'] as $bar)
                                    @php $b = $bar['booking']; @endphp
                                    <a href="{{ route('admin.bookings.show', $b) }}" wire:navigate
                                       class="absolute top-1.5 bottom-1.5 z-20 flex items-center overflow-hidden rounded-md border border-white px-2 text-[11px] font-medium text-white"
                                       style="left: {{ $bar['left'] }}%; width: calc({{ $bar['width'] }}% - 2px); background: {{ $b->statusColor() }}"
                                       title="{{ $b->bookingCode() }} · {{ $b->customer_name }}">
                                        <span class="truncate">{{ $b->customer_name ?: $b->bookingCode() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <p class="text-[12px] text-[#9ca3af]">Numbers a guest hasn't been checked into yet are arranged here as a best fit — the actual room number is locked in at check-in. Click a bar to open the booking.</p>
    @endif
</div>
