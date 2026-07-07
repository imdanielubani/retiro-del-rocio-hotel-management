{{-- Add Booking modal — creates a confirmed cinema booking + emails the guest. --}}
@if ($showCreate)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showCreate', false)"></div>
        <form wire:submit="createBooking" class="relative z-10 my-auto w-full max-w-[600px] overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                <div>
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">Add Booking</h3>
                    <p class="text-[12px] text-[#6b7280]">Create a cinema booking and notify the guest by email.</p>
                </div>
                <button type="button" wire:click="$set('showCreate', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="max-h-[72vh] overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-[12px] font-semibold text-[#374151]">Guest name</label>
                        <input type="text" wire:model="cName" placeholder="e.g. Micheal Philip" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Email</label>
                        <input type="email" wire:model="cEmail" placeholder="guest@email.com" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cEmail') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Phone <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                        <input type="text" wire:model="cPhone" placeholder="(+234) …" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-[12px] font-semibold text-[#374151]">Movie</label>
                        <select wire:model="cMovieId" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">Select a movie…</option>
                            @foreach ($movies as $mv)<option value="{{ $mv->id }}">{{ $mv->title }}</option>@endforeach
                        </select>
                        @error('cMovieId') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Date</label>
                        <input type="date" wire:model="cDate" min="{{ now()->toDateString() }}" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cDate') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Showtime</label>
                        <input type="text" wire:model="cTime" placeholder="7:00 PM" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cTime') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Private room</label>
                        <select wire:model="cRoom" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @foreach (\App\Models\Movie::ROOMS as $room)<option value="{{ $room }}">{{ $room }}</option>@endforeach
                        </select>
                        @error('cRoom') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Guests <span class="font-normal text-[#9ca3af]">(up to {{ \App\Models\Movie::SEATS_PER_ROOM }})</span></label>
                        <input type="number" min="1" max="{{ \App\Models\Movie::SEATS_PER_ROOM }}" wire:model="cGuests" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cGuests') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3 sm:col-span-2">
                        <input type="checkbox" wire:model="cMarkPaid" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[13px] text-[#374151]">Mark booking as <strong>paid</strong></span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                <button type="button" wire:click="$set('showCreate', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <span wire:loading.remove wire:target="createBooking">Create Booking</span>
                    <span wire:loading wire:target="createBooking">Creating…</span>
                </button>
            </div>
        </form>
    </div>
@endif
