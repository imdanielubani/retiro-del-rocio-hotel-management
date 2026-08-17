@php
    $icons = [
        'building' => '<path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M15 21V9h2a2 2 0 0 1 2 2v10M9 7h2M9 11h2M9 15h2"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20Z"/>',
    ];
@endphp

<div class="flex flex-col gap-4 lg:flex-row lg:items-start">
    {{-- ===== Tab rail ===== --}}
    <nav class="flex shrink-0 gap-1 overflow-x-auto rounded-2xl border border-[#e5e7eb] bg-white p-2 lg:w-[232px] lg:flex-col lg:overflow-visible">
        @foreach ($tabs as $key => $tab)
            <button type="button" wire:click="selectTab('{{ $key }}')"
                    @class([
                        'flex shrink-0 items-center gap-2.5 whitespace-nowrap rounded-xl px-4 py-2.5 text-left text-[13px] transition',
                        'bg-[#fff7ed] font-semibold text-[#f38c00]' => $this->tab === $key,
                        'text-[#374151] hover:bg-[#f9fafb]' => $this->tab !== $key,
                    ])>
                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    {!! $icons[$tab['icon']] !!}
                </svg>
                {{ $tab['label'] }}
            </button>
        @endforeach
    </nav>

    {{-- ===== Panel ===== --}}
    <section class="min-w-0 flex-1 rounded-2xl border border-[#e5e7eb] bg-white">
        @if ($this->tab === 'hotel')
            <form wire:submit="save">
                <div class="border-b border-[#e5e7eb] px-6 py-5">
                    <h2 class="text-[17px] font-bold text-[#1e1e1e]">Hotel Information</h2>
                    <p class="mt-0.5 text-[13px] text-[#6b7280]">Used across booking details, guest emails and the in-room tablets.</p>
                </div>

                <div class="grid grid-cols-1 gap-x-5 gap-y-4 px-6 py-6 md:grid-cols-2">
                    <x-admin.settings-field label="Apartment Name" name="name" required />
                    <x-admin.settings-field label="Tagline" name="tagline" />
                    <x-admin.settings-field label="Address" name="address" />
                    <x-admin.settings-field label="City" name="city" />
                    <x-admin.settings-field label="Country" name="country" />
                    <x-admin.settings-field label="Phone Number" name="phone" />

                    <div class="md:col-span-2">
                        <x-admin.settings-field label="Email Address" name="email" type="email" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Description</label>
                        <textarea wire:model="description" rows="4"
                                  class="w-full rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"></textarea>
                        @error('description') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                    </div>

                    {{-- Front-desk policy: read by booking details and every paired tablet. --}}
                    <div class="md:col-span-2 mt-2 border-t border-[#f1f1ee] pt-5">
                        <p class="text-[13px] font-semibold text-[#1e1e1e]">Front-desk policy</p>
                        <p class="mt-0.5 text-[12px] text-[#6b7280]">
                            Bookings store arrival and departure as dates; these times complete them. Changing them updates every booking’s stay card and the in-room tablets.
                        </p>
                    </div>

                    <x-admin.settings-field label="Check-in Time" name="checkInTime" type="time" required />
                    <x-admin.settings-field label="Check-out Time" name="checkOutTime" type="time" required />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-[#e5e7eb] px-6 py-4">
                    <span wire:loading wire:target="save" class="text-[13px] text-[#6b7280]">Saving…</span>
                    <button type="submit"
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60"
                            wire:loading.attr="disabled" wire:target="save">
                        Save Changes
                    </button>
                </div>
            </form>
        @elseif ($this->tab === 'security')
            <form wire:submit="changePassword">
                <div class="border-b border-[#e5e7eb] px-6 py-5">
                    <h2 class="text-[17px] font-bold text-[#1e1e1e]">Change Password</h2>
                    <p class="mt-0.5 text-[13px] text-[#6b7280]">Update the password for your own admin account.</p>
                </div>

                <div class="grid grid-cols-1 gap-x-5 gap-y-4 px-6 py-6 md:max-w-[420px]">
                    <x-admin.settings-field label="Current Password" name="currentPassword" type="password" required />
                    <x-admin.settings-field label="New Password" name="newPassword" type="password" required />
                    <x-admin.settings-field label="Confirm New Password" name="newPassword_confirmation" type="password" required />
                    <p class="text-[12px] text-[#9ca3af]">At least 12 characters, with an uppercase letter, a number and a special character.</p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-[#e5e7eb] px-6 py-4">
                    <span wire:loading wire:target="changePassword" class="text-[13px] text-[#6b7280]">Saving…</span>
                    <button type="submit"
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60"
                            wire:loading.attr="disabled" wire:target="changePassword">
                        Change Password
                    </button>
                </div>
            </form>
        @elseif ($this->tab === 'website')
            <form wire:submit="saveWebsite">
                <div class="border-b border-[#e5e7eb] px-6 py-5">
                    <h2 class="text-[17px] font-bold text-[#1e1e1e]">Maintenance Mode</h2>
                    <p class="mt-0.5 text-[13px] text-[#6b7280]">Take the public website offline while you make changes. The admin dashboard always stays reachable.</p>
                </div>

                <div class="flex flex-col gap-5 px-6 py-6">
                    <label class="flex items-start gap-3 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] p-4">
                        <input type="checkbox" wire:model="maintenanceMode"
                               class="mt-0.5 size-4 rounded border-[#e5e7eb] text-[#f38c00] focus:ring-[#f38c00]/30">
                        <span>
                            <span class="block text-[14px] font-semibold text-[#1e1e1e]">
                                Put the public site in maintenance mode
                                @if ($maintenanceMode)
                                    <span class="ml-1 rounded-full bg-[#fff3e0] px-2 py-0.5 text-[11px] font-semibold text-[#b45309]">Currently ON</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block text-[12px] text-[#6b7280]">Visitors see the message below instead of the site. Staff tablets and the admin dashboard are unaffected.</span>
                        </span>
                    </label>

                    <div>
                        <label class="mb-1.5 block text-[13px] font-medium text-[#374151]">Message shown to visitors</label>
                        <textarea wire:model="maintenanceMessage" rows="3" maxlength="500"
                                  class="w-full rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-4 py-3 text-[14px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20"></textarea>
                        @error('maintenanceMessage') <p class="mt-1 text-[12px] text-[#dc2626]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-[#e5e7eb] px-6 py-4">
                    <span wire:loading wire:target="saveWebsite" class="text-[13px] text-[#6b7280]">Saving…</span>
                    <button type="submit"
                            @if ($maintenanceMode) wire:confirm="This will take the public website offline for every visitor. Continue?" @endif
                            class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00] disabled:opacity-60"
                            wire:loading.attr="disabled" wire:target="saveWebsite">
                        Save Changes
                    </button>
                </div>
            </form>
        @else
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-24 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-[#f9fafb] text-[#9ca3af]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        {!! $icons[$tabs[$this->tab]['icon']] !!}
                    </svg>
                </div>
                <p class="text-[15px] font-semibold text-[#1e1e1e]">{{ $tabs[$this->tab]['label'] }}</p>
                <p class="max-w-[380px] text-[13px] text-[#6b7280]">This section isn’t built yet.</p>
            </div>
        @endif
    </section>
</div>
