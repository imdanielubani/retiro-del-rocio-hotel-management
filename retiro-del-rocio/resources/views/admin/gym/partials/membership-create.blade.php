{{-- Add Membership modal — creates an active membership + emails the member. --}}
@if ($showCreate)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="$set('showCreate', false)"></div>
        <form wire:submit="createMembership" class="relative z-10 my-auto w-full max-w-[560px] overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                <div>
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">Add Membership</h3>
                    <p class="text-[12px] text-[#6b7280]">Create a gym membership and notify the member by email.</p>
                </div>
                <button type="button" wire:click="$set('showCreate', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="text-[12px] font-semibold text-[#374151]">Member name</label>
                        <input type="text" wire:model="cName" placeholder="e.g. Micheal Philip" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Email</label>
                        <input type="email" wire:model="cEmail" placeholder="member@email.com" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('cEmail') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Phone <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                        <input type="text" wire:model="cPhone" placeholder="(+234) …" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Plan</label>
                        <select wire:model="cPlan" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="">Select a plan…</option>
                            @foreach ($plans as $pl)<option value="{{ $pl->slug }}">{{ $pl->name }} — ₦{{ number_format($pl->price) }}</option>@endforeach
                        </select>
                        @error('cPlan') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[12px] font-semibold text-[#374151]">Type</label>
                        <select wire:model="cType" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            <option value="subscribe">Subscription</option>
                            <option value="renewal">Renewal</option>
                        </select>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3 sm:col-span-2">
                        <input type="checkbox" wire:model="cMarkPaid" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span class="text-[13px] text-[#374151]">Mark as <strong>paid</strong> (records the payment immediately)</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                <button type="button" wire:click="$set('showCreate', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                    <span wire:loading.remove wire:target="createMembership">Create Membership</span>
                    <span wire:loading wire:target="createMembership">Creating…</span>
                </button>
            </div>
        </form>
    </div>
@endif
