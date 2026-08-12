{{-- Bar & Lounge order detail drawer. $selected = ?DiningOrder. --}}
@if ($showDetail && $selected)
    @php [$sC,$sB]=$selected->statusColors(); $next = \App\Livewire\Admin\BarLounge\Orders::FLOW[$selected->status] ?? null; @endphp
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="closeDetail"></div>
        <div class="relative z-10 my-auto w-full max-w-[480px] overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                <div>
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $selected->orderCode() }}</h3>
                    <p class="text-[12px] text-[#9ca3af]">{{ optional($selected->created_at)->format('D, M j, Y • g:i A') }}</p>
                </div>
                <button type="button" wire:click="closeDetail" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex max-h-[65vh] flex-col gap-4 overflow-y-auto px-6 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[14px] font-semibold text-[#1e1e1e]">{{ $selected->customer_name ?: 'Guest' }}</p>
                        <p class="text-[12px] text-[#9ca3af]">{{ $selected->customer_email }}</p>
                    </div>
                    <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold" style="background: {{ $sB }}; color: {{ $sC }};">{{ $selected->statusLabel() }}</span>
                </div>

                <div class="flex flex-col divide-y divide-[#f1f1ee] rounded-xl border border-[#e5e7eb]">
                    @foreach ($selected->items ?? [] as $i)
                        <div class="flex items-center gap-3 px-3.5 py-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#f9fafb]">
                                @if (! empty($i['image_url']))
                                    <img src="{{ $i['image_url'] }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="size-5 text-[#d6d9d2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 3h16v18l-8-4-8 4z"/></svg>
                                @endif
                            </div>
                            <div class="flex-1">
                                <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $i['name'] ?? '' }}</p>
                                <p class="text-[11px] text-[#9ca3af]">Qty {{ $i['qty'] ?? 1 }} · ₦{{ number_format($i['price'] ?? 0) }} each</p>
                            </div>
                            <p class="text-[13px] font-bold text-[#1e1e1e]">₦{{ number_format(($i['price'] ?? 0) * ($i['qty'] ?? 1)) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col gap-1.5 rounded-xl bg-[#f9fafb] px-4 py-3 text-[13px]">
                    <div class="flex items-center justify-between text-[#6b7280]"><span>Subtotal</span><span>₦{{ number_format($selected->subtotal) }}</span></div>
                    @if($selected->service_fee > 0)
                    <div class="flex items-center justify-between text-[#6b7280]"><span>Service Fee</span><span>₦{{ number_format($selected->service_fee) }}</span></div>
                    @endif
                    <div class="flex items-center justify-between border-t border-[#e5e7eb] pt-1.5 text-[14px] font-bold text-[#1e1e1e]"><span>Total</span><span>{{ $selected->totalLabel() }}</span></div>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-[#e5e7eb] px-4 py-3 text-[12px] text-[#6b7280]">
                    <span>Payment</span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $selected->paymentStatusBadge() }}">{{ $selected->paymentLabel() }} · {{ $selected->methodLabel() }}</span>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                <button type="button" wire:click="closeDetail" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Close</button>
                @if ($next)
                    <button type="button" wire:click="advanceStatus({{ $selected->id }})" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">Advance Status</button>
                @endif
            </div>
        </div>
    </div>
@endif
