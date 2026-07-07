@php
    $b = $booking;
    $initials = collect(explode(' ', trim((string) $b->customer_name)))->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: '—';
    $naira = fn ($n) => '₦'.number_format((int) $n);

    // Timeline events derived from the booking.
    $paymentDone = $b->paid_at || in_array($b->status, ['paid', 'checked_in', 'checked_out']);
    $checkedIn = in_array($b->status, ['checked_in', 'checked_out']);
    $checkedOut = $b->status === 'checked_out';
    $timeline = [
        ['title' => 'Booking Created', 'at' => optional($b->created_at)->format('M j, Y · g:i A'), 'done' => true, 'color' => '#f38c00'],
        ['title' => 'Payment Confirmed', 'at' => optional($b->paid_at ?? $b->created_at)->format('M j, Y · g:i A'), 'done' => $paymentDone, 'color' => '#16a34a'],
        ['title' => 'Check-in', 'at' => $b->check_in ? $b->check_in->format('M j, Y').' · 2:00 PM' : '—', 'done' => $checkedIn, 'color' => '#16a34a'],
        ['title' => 'Check-out', 'at' => $b->check_out ? $b->check_out->format('M j, Y').' · 12:00 PM' : '—', 'done' => $checkedOut, 'color' => '#16a34a'],
    ];
    if ($b->status === 'cancelled') {
        $timeline[] = ['title' => 'Cancelled', 'at' => optional($b->updated_at)->format('M j, Y · g:i A'), 'done' => true, 'color' => '#dc2626'];
    }
@endphp

<div class="flex flex-col gap-4">
    {{-- Top bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admin.bookings.index') }}" wire:navigate
           class="flex items-center gap-2 text-[14px] font-medium text-[#6b7280] transition hover:text-[#1e1e1e]">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Bookings
        </a>

        <div class="flex flex-wrap items-center gap-2.5" x-data="{ confirming: false }">
            @if ($b->status !== 'cancelled')
                <button type="button" wire:click="startEdit"
                        class="flex items-center gap-1.5 rounded-[10px] border border-[#e5e7eb] bg-white px-4 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Edit
                </button>
            @endif

            @if (in_array($b->status, ['paid', 'checked_in', 'checked_out']))
                <button type="button" wire:click="startRenew"
                        class="flex items-center gap-1.5 rounded-[10px] border border-[#e5e7eb] bg-white px-4 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/></svg>
                    Renew
                </button>
            @endif

            @if (! in_array($b->status, ['cancelled', 'checked_out']))
                <template x-if="!confirming">
                    <button type="button" @click="confirming = true"
                            class="rounded-[10px] bg-[#fde2e2] px-5 py-2.5 text-[14px] font-semibold text-[#dc2626] transition hover:bg-[#fbd0d0]">
                        Cancel
                    </button>
                </template>
                <template x-if="confirming">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="confirming = false" class="rounded-[10px] border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] hover:bg-[#f9fafb]">Keep</button>
                        <button type="button" wire:click="cancel" @click="confirming = false" class="rounded-[10px] bg-[#dc2626] px-4 py-2.5 text-[14px] font-semibold text-white hover:bg-[#b91c1c]">Confirm cancel</button>
                    </div>
                </template>
            @endif

            @if ($b->status === 'pending')
                <button type="button" wire:click="confirm"
                        class="flex items-center gap-1.5 rounded-[10px] bg-[#16a34a] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#15803d]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    Confirm
                </button>
            @elseif ($b->status === 'paid')
                <button type="button" wire:click="startCheckIn"
                        class="flex items-center gap-1.5 rounded-[10px] bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    Check In
                </button>
            @elseif ($b->status === 'checked_in')
                <button type="button" wire:click="checkOut"
                        class="flex items-center gap-1.5 rounded-[10px] bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Check Out
                </button>
            @elseif ($b->status === 'checked_out')
                <span class="flex items-center gap-1.5 rounded-[10px] bg-[#eef2f6] px-5 py-2.5 text-[14px] font-semibold text-[#475569]">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    Checked Out
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_minmax(0,360px)]">
        {{-- ===== Left column ===== --}}
        <div class="flex flex-col gap-4">
            {{-- Guest Information --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-6 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Guest Information</p>
                </div>
                <div class="flex items-center gap-4 px-6 py-5">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-[#fff3e0] text-[16px] font-bold text-[#f38c00]">{{ $initials }}</div>
                    <div class="min-w-0">
                        <p class="text-[18px] font-bold text-[#1e1e1e]">{{ $b->customer_name ?: '—' }}</p>
                        <p class="text-[13px] text-[#6b7280]">{{ $b->customer_email ?: '—' }}</p>
                        <p class="text-[13px] text-[#6b7280]">{{ $b->customer_phone ?: '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Booking Information --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-6 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Booking Information</p>
                </div>
                <div class="grid grid-cols-1 gap-y-5 px-6 py-5 sm:grid-cols-2 sm:gap-x-8">
                    @php
                        $fields = [
                            'Booking ID' => $b->bookingCode(),
                            'Booking Date' => optional($b->created_at)->format('M j, Y'),
                            'Room' => $b->room_name ?: '—',
                            'Room Type' => $b->room?->type ?: '—',
                            'Room Number' => $b->roomUnit?->number ?: 'Not assigned',
                            'Check-in' => $b->check_in ? $b->check_in->format('M j, Y').' · 2:00 PM' : '—',
                            'Check-out' => $b->check_out ? $b->check_out->format('M j, Y').' · 12:00 PM' : '—',
                            'Duration' => $b->nights.' '.\Illuminate\Support\Str::plural('night', $b->nights),
                            'Guests' => $b->guests.' '.\Illuminate\Support\Str::plural('Guest', $b->guests),
                        ];
                    @endphp
                    @foreach ($fields as $label => $value)
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $label }}</p>
                            <p class="mt-1 text-[14px] font-medium text-[#1e1e1e]">{{ $value }}</p>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">Status</p>
                        <span class="mt-1.5 inline-flex items-center rounded-full px-2.5 py-1 text-[12px] font-medium {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Right column ===== --}}
        <div class="flex flex-col gap-4">
            {{-- Payment Summary --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-6 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Payment Summary</p>
                </div>
                <div class="flex flex-col gap-3 px-6 py-5 text-[14px]">
                    <div class="flex items-center justify-between">
                        <span class="text-[#6b7280]">Room rate ({{ $payment['nights'] }} {{ \Illuminate\Support\Str::plural('night', $payment['nights']) }})</span>
                        <span class="font-medium text-[#1e1e1e]">{{ $naira($payment['room_rate']) }}</span>
                    </div>
                    @if ($payment['pickup'] > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-[#6b7280]">Vehicle pickup</span>
                            <span class="font-medium text-[#1e1e1e]">{{ $naira($payment['pickup']) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-[#6b7280]">Taxes &amp; fees ({{ $payment['tax_pct'] }}%)</span>
                        <span class="font-medium text-[#1e1e1e]">{{ $naira($payment['taxes']) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#6b7280]">Service charge</span>
                        <span class="font-medium text-[#1e1e1e]">{{ $naira($payment['service']) }}</span>
                    </div>

                    <div class="mt-1 flex items-center justify-between border-t border-[#e5e7eb] pt-3">
                        <span class="text-[15px] font-bold text-[#1e1e1e]">Total</span>
                        <span class="text-[15px] font-bold text-[#f38c00]">{{ $naira($payment['total']) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#6b7280]">Payment method</span>
                        <span class="font-medium text-[#1e1e1e]">{{ $b->methodLabel() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#6b7280]">Payment status</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $b->paymentStatusBadge() }}">{{ $b->paymentSummaryStatus() }}</span>
                    </div>
                </div>
            </div>

            {{-- Booking Timeline --}}
            <div class="rounded-2xl border border-[#e5e7eb] bg-white">
                <div class="border-b border-[#e5e7eb] px-6 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Booking Timeline</p>
                </div>
                <div class="flex flex-col px-6 py-5">
                    @foreach ($timeline as $i => $event)
                        <div class="relative flex gap-3 {{ $loop->last ? '' : 'pb-5' }}">
                            {{-- Connector line --}}
                            @if (! $loop->last)
                                <span class="absolute left-[14px] top-7 h-[calc(100%-1rem)] w-px bg-[#e5e7eb]"></span>
                            @endif
                            {{-- Icon --}}
                            <div class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full {{ $event['done'] ? '' : 'border border-[#e5e7eb] bg-white' }}"
                                 @if ($event['done']) style="background: {{ $event['color'] }}" @endif>
                                @if ($event['done'])
                                    <svg class="size-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                @else
                                    <svg class="size-3.5 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                @endif
                            </div>
                            {{-- Text --}}
                            <div class="pt-0.5">
                                <p class="text-[14px] font-semibold {{ $event['done'] ? 'text-[#1e1e1e]' : 'text-[#6b7280]' }}">{{ $event['title'] }}</p>
                                <p class="text-[12px] text-[#9ca3af]">{{ $event['at'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Edit Booking modal ===== --}}
    @if ($editing)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.set('editing', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('editing', false)"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-[560px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Edit Booking · {{ $b->bookingCode() }}</p>
                    <button type="button" wire:click="$set('editing', false)" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Guest name</label>
                            <input type="text" wire:model="eName" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eName') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Email</label>
                            <input type="email" wire:model="eEmail" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eEmail') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Phone</label>
                            <input type="text" wire:model="ePhone" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Room</label>
                            <select wire:model="eRoomId" class="h-11 w-full rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="">— Keep current ({{ $b->room_name ?: 'none' }}) —</option>
                                @foreach ($rooms as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}{{ $r->type ? ' · '.$r->type : '' }}{{ $r->price ? ' · ₦'.number_format($r->price) : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Check-in</label>
                            <input type="date" wire:model="eCheckIn" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eCheckIn') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Check-out</label>
                            <input type="date" wire:model="eCheckOut" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eCheckOut') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Guests</label>
                            <input type="number" min="1" wire:model="eGuests" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eGuests') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Amount (₦)</label>
                            <input type="number" min="0" wire:model="eAmount" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('eAmount') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-[#e5e7eb] px-5 py-4">
                    <button type="button" wire:click="$set('editing', false)" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] hover:bg-[#f9fafb]">Cancel</button>
                    <button type="button" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit"
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60">Save changes</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Check-in: assign room number ===== --}}
    @if ($assigning)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.set('assigning', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('assigning', false)"></div>
            <div class="relative w-full max-w-[440px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Check In · Assign Room</p>
                    <button type="button" wire:click="$set('assigning', false)" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-5 py-5">
                    <p class="text-[13px] text-[#6b7280]">Assign an available room number in <span class="font-semibold text-[#1e1e1e]">{{ $b->room_name ?: 'this room' }}</span> to <span class="font-semibold text-[#1e1e1e]">{{ $b->customer_name ?: 'the guest' }}</span>.</p>

                    @if ($assignableUnits->isEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-[#d6d9d2] bg-[#f9fafb] px-4 py-5 text-center text-[13px] text-[#6b7280]">
                            No available room numbers for this room. You can still check in without assigning one, or add numbers on the room's edit page.
                        </div>
                    @else
                        <label class="mb-1.5 mt-4 block text-[13px] font-medium text-[#374151]">Room number</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($assignableUnits as $unit)
                                <button type="button" wire:click="$set('assignUnitId', {{ $unit->id }})"
                                        @class([
                                            'rounded-lg border px-3.5 py-2 text-[14px] font-semibold transition',
                                            'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $assignUnitId === $unit->id,
                                            'border-[#e5e7eb] bg-white text-[#374151] hover:bg-[#f9fafb]' => $assignUnitId !== $unit->id,
                                        ])>{{ $unit->number }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-[#e5e7eb] px-5 py-4">
                    <button type="button" wire:click="$set('assigning', false)" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] hover:bg-[#f9fafb]">Cancel</button>
                    <button type="button" wire:click="confirmCheckIn"
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        {{ $assignableUnits->isEmpty() ? 'Check in anyway' : 'Confirm check-in' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Renew / Extend modal ===== --}}
    @if ($renewing)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.set('renewing', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('renewing', false)"></div>
            <div class="relative w-full max-w-[440px] overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-5 py-4">
                    <p class="text-[15px] font-bold text-[#1e1e1e]">Renew / Extend Stay</p>
                    <button type="button" wire:click="$set('renewing', false)" class="flex size-9 items-center justify-center rounded-lg border border-[#e5e7eb] text-[#6b7280] hover:bg-[#f9fafb]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-5 py-5">
                    <p class="text-[13px] text-[#6b7280]">Current check-out: <span class="font-semibold text-[#1e1e1e]">{{ optional($b->check_out)->format('M j, Y') }}</span></p>
                    <p class="mt-1 text-[13px] text-[#6b7280]">Nightly rate: <span class="font-semibold text-[#1e1e1e]">₦{{ number_format($nightly) }}</span></p>

                    <label class="mb-1.5 mt-4 block text-[13px] font-medium text-[#374151]">New check-out date</label>
                    <input type="date" wire:model="renewCheckOut" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    @error('renewCheckOut') <span class="mt-1 block text-[12px] text-[#dc2626]">{{ $message }}</span> @enderror

                    <p class="mt-3 text-[12px] text-[#6b7280]">Extra nights are added to the booking total at the room's nightly rate (plus 7.5% VAT). A checked-out guest is set back to <span class="font-medium">Checked In</span>.</p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-[#e5e7eb] px-5 py-4">
                    <button type="button" wire:click="$set('renewing', false)" class="rounded-xl border border-[#e5e7eb] px-4 py-2.5 text-[14px] font-medium text-[#374151] hover:bg-[#f9fafb]">Cancel</button>
                    <button type="button" wire:click="saveRenew" wire:loading.attr="disabled" wire:target="saveRenew"
                            class="rounded-xl bg-[#16a34a] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#15803d] disabled:opacity-60">Extend stay</button>
                </div>
            </div>
        </div>
    @endif
</div>
