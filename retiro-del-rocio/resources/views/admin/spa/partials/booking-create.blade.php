{{-- Add Booking modal. Creates a confirmed manual spa session + emails guest. --}}
@if ($showCreate)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showCreate', false)"></div>

        <div class="relative z-10 my-auto w-full max-w-[620px] overflow-hidden rounded-2xl bg-white shadow-xl">
            <form wire:submit="createBooking">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <div>
                        <h3 class="text-[17px] font-bold text-[#1e1e1e]">Add Booking</h3>
                        <p class="text-[12px] text-[#6b7280]">Create a spa session and notify the guest by email.</p>
                    </div>
                    <button type="button" wire:click="$set('showCreate', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Guest name</label>
                            <input type="text" wire:model="cName" placeholder="e.g. Amara Okonkwo"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('cName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Email</label>
                            <input type="email" wire:model="cEmail" placeholder="guest@email.com"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('cEmail') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Phone <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="text" wire:model="cPhone" placeholder="(+234) …"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Service</label>
                            <select wire:model="cServiceSlug"
                                    class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="">Select a service…</option>
                                @foreach ($services as $s)
                                    <option value="{{ $s->slug }}">{{ $s->name }} — ₦{{ number_format($s->price) }}</option>
                                @endforeach
                            </select>
                            @error('cServiceSlug') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Guests</label>
                            <input type="number" min="1" max="30" wire:model="cGuests"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('cGuests') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Date</label>
                            <input type="date" wire:model="cDate" min="{{ now()->toDateString() }}"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('cDate') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Time <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <input type="time" wire:model="cTime"
                                   class="h-11 rounded-xl border border-[#e5e7eb] bg-white px-3.5 text-[14px] text-[#1e1e1e] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3 sm:col-span-2">
                            <input type="checkbox" wire:model="cMarkPaid" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                            <span class="text-[13px] text-[#374151]">Mark as <strong>paid</strong> (records the payment immediately)</span>
                        </label>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showCreate', false)" class="rounded-xl border border-[#e5e7eb] bg-white px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <span wire:loading.remove wire:target="createBooking">Create Booking</span>
                        <span wire:loading wire:target="createBooking">Creating…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
