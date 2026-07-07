{{-- Booking detail — centered modal (consistent with the other modules). --}}
@if ($showDetail && $selected)
    @php
        [$sColor, $sBg] = $selected->statusColors();
        [$pColor, $pBg] = $selected->paymentColors();
        $canComplete = ! in_array($selected->status, ['cancelled', 'completed'], true);
        $canCancel = ! in_array($selected->status, ['cancelled', 'completed'], true);
        $canRecord = $selected->payment_status !== 'paid' && $selected->status !== 'cancelled';
    @endphp
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4" wire:key="spa-detail-{{ $selected->id }}">
        <div class="absolute inset-0 bg-black/50" wire:click="closeDetail"></div>

        <div class="relative z-10 flex max-h-[90vh] w-full max-w-[480px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
             x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            {{-- Header --}}
            <div class="flex items-start justify-between border-b border-[#e5e7eb] px-6 py-5">
                <div>
                    <p class="text-[12px] font-bold uppercase tracking-[0.5px] text-[#f38c00]">{{ $selected->sessionCode() }}</p>
                    <h3 class="mt-1 text-[18px] font-bold text-[#1e1e1e]">{{ $selected->customer_name ?: 'Guest' }}</h3>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $sBg }}; color: {{ $sColor }};">{{ $selected->statusLabel() }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: {{ $pBg }}; color: {{ $pColor }};">{{ $selected->paymentLabel() }}</span>
                    </div>
                </div>
                <button type="button" wire:click="closeDetail" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                {{-- Contact --}}
                <div class="flex flex-col gap-2.5">
                    @if ($selected->customer_email)
                        <div class="flex items-center gap-2.5 text-[13px] text-[#374151]">
                            <svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $selected->customer_email }}
                        </div>
                    @endif
                    @if ($selected->customer_phone)
                        <div class="flex items-center gap-2.5 text-[13px] text-[#374151]">
                            <svg class="size-4 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            {{ $selected->customer_phone }}
                        </div>
                    @endif
                </div>

                {{-- Schedule --}}
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Date</p>
                        <p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->date?->format('M j, Y') ?: '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Time</p>
                        <p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->time ?: '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Guests</p>
                        <p class="mt-0.5 text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->guests }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3">
                        <p class="text-[11px] uppercase tracking-wide text-[#9ca3af]">Reference</p>
                        <p class="mt-0.5 truncate text-[13px] font-semibold text-[#1e1e1e]">{{ $selected->reference }}</p>
                    </div>
                </div>

                {{-- Services --}}
                <p class="mt-6 text-[12px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Services</p>
                <div class="mt-2 flex flex-col gap-2">
                    @foreach (($selected->services ?? []) as $svc)
                        <div class="flex items-center justify-between rounded-xl border border-[#e5e7eb] px-4 py-2.5">
                            <div>
                                <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $svc['name'] ?? '—' }}</p>
                                <p class="text-[11px] text-[#9ca3af]">
                                    {{ ($svc['guests'] ?? $selected->guests) }} {{ \Illuminate\Support\Str::plural('guest', $svc['guests'] ?? $selected->guests) }}
                                    @if (! empty($svc['duration_minutes'])) · {{ $svc['duration_minutes'] }} min @endif
                                </p>
                            </div>
                            <p class="text-[13px] font-semibold text-[#1e1e1e]">{{ $svc['subtotal_label'] ?? ('₦'.number_format($svc['subtotal'] ?? 0)) }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($selected->special_request)
                    <p class="mt-6 text-[12px] font-semibold uppercase tracking-[0.5px] text-[#6b7280]">Special request</p>
                    <p class="mt-1.5 rounded-xl bg-[#f9fafb] px-4 py-3 text-[13px] text-[#374151]">{{ $selected->special_request }}</p>
                @endif

                {{-- Totals --}}
                <div class="mt-6 flex flex-col gap-1.5 border-t border-[#e5e7eb] pt-4 text-[13px]">
                    <div class="flex justify-between text-[#6b7280]"><span>Subtotal</span><span>₦{{ number_format($selected->subtotal) }}</span></div>
                    <div class="flex justify-between text-[#6b7280]"><span>Service fee</span><span>₦{{ number_format($selected->fees) }}</span></div>
                    <div class="flex justify-between text-[#6b7280]"><span>VAT (7.5%)</span><span>₦{{ number_format($selected->taxes) }}</span></div>
                    <div class="mt-1 flex justify-between text-[15px] font-bold text-[#1e1e1e]"><span>Total</span><span>{{ $selected->totalLabel() }}</span></div>
                </div>
            </div>

            {{-- Footer actions --}}
            @if ($canComplete || $canCancel || $canRecord)
                <div class="flex flex-wrap items-center gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    @if ($canComplete)
                        <button type="button" wire:click="markCompleted({{ $selected->id }})" class="flex-1 rounded-lg bg-[#7c3aed] px-4 py-2.5 text-[13px] font-bold text-white transition hover:bg-[#6d28d9]">Mark Completed</button>
                    @endif
                    @if ($canRecord)
                        <button type="button" wire:click="recordPayment({{ $selected->id }})" class="flex-1 rounded-lg border border-[#e5e7eb] px-4 py-2.5 text-[13px] font-semibold text-[#374151] transition hover:bg-[#f9fafb]">Record Payment</button>
                    @endif
                    @if ($canCancel)
                        <button type="button" wire:click="cancelSession({{ $selected->id }})" class="flex-1 rounded-lg bg-[#fee2e2] px-4 py-2.5 text-[13px] font-bold text-[#dc2626] transition hover:bg-[#fecaca]">Reject / Cancel</button>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
