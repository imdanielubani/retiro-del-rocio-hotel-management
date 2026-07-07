@props([
    // Alpine expression run when the "Book" button is clicked (optional).
    'bookAction' => '',
])

@php
    // Pickup locations are editable in Admin → Website CMS → Vehicle Pickup.
    $pickupLocations = collect(cms_array('pickup.locations'))->pluck('name')->filter()->values()->all();
    if (empty($pickupLocations)) {
        $pickupLocations = ['Airport Pickup', 'Valgee', 'Nengee', 'Plateau Riders'];
    }
@endphp

{{--
    Airport pick-up search / booking bar — Figma node 85:1991.
    Relies on the parent Alpine scope: location (PERMANENT), passengers,
    arrivalDate, pickupTime, flightNumber. Location is fixed; the guest fills
    in the other fields. One clean row on desktop, stacked on mobile/tablet.
--}}
<div class="mt-6 rounded-[19px] bg-[#d9d9d9] px-4 py-6 sm:px-6 lg:px-[55px] lg:py-7">
    <div class="flex flex-col gap-[9px] lg:flex-row lg:items-stretch">
        {{-- Location (guest selects a pickup point) --}}
        <div class="flex h-[73px] flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-[#f6f6f6] px-[17px] lg:min-w-0 lg:flex-[1.6]">
            <p class="text-label font-medium tracking-tight text-[#3c3c3c]">Location</p>
            <div class="flex items-center gap-[7px]">
                <svg class="icon-sm shrink-0 text-[#202020]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>
                <select x-model="location" class="w-full min-w-0 cursor-pointer appearance-none truncate bg-transparent text-body-sm font-bold tracking-tight text-[#202020] focus:outline-none">
                    @foreach ($pickupLocations as $loc)
                        <option value="{{ $loc }}">{{ $loc }}</option>
                    @endforeach
                </select>
                <svg class="icon-sm shrink-0 text-[#7a7a7a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>

        {{-- No. of Passengers (guest selects) --}}
        <div class="flex h-[73px] flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-[#f6f6f6] px-[18px] lg:min-w-0 lg:flex-[0.85]">
            <p class="text-label font-medium tracking-tight text-[#3c3c3c]">No. of Passengers</p>
            <div class="flex items-center gap-[5px]">
                <svg class="icon-md shrink-0 text-[#202020]" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16a3 3 0 0 1 3-3h2a3 3 0 0 1 3 3v4H5v-4zM9 4a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM16 11h2a2 2 0 0 1 2 2v7h-4v-9z"/></svg>
                <select x-model.number="passengers"
                        class="w-full cursor-pointer appearance-none bg-transparent text-body-sm font-bold tracking-tight text-[#202020] focus:outline-none">
                    @for ($n = 1; $n <= 14; $n++)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endfor
                </select>
            </div>
        </div>

        {{-- Arrival Date (guest selects) --}}
        <div class="flex h-[73px] flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-[#f6f6f6] px-[19px] lg:min-w-0 lg:flex-1">
            <p class="text-label font-medium tracking-tight text-[#3c3c3c]">Arrival Date</p>
            <div class="flex items-center gap-[5px]">
                <img loading="lazy" src="{{ asset('images/date.png') }}" alt="" class="icon-sm shrink-0 object-contain">
                <input type="date" x-model="arrivalDate" :min="today"
                       @click="$event.target.showPicker && $event.target.showPicker()"
                       class="w-full min-w-0 cursor-pointer bg-transparent text-body-sm font-semibold tracking-tight text-[#202020] focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
            </div>
        </div>

        {{-- Pick-up Time (guest selects) --}}
        <div class="flex h-[73px] flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-[#f6f6f6] px-[19px] lg:min-w-0 lg:flex-1">
            <p class="text-label font-medium tracking-tight text-[#3c3c3c]">Pick-up Time</p>
            <div class="flex items-center gap-[7px]">
                <svg class="icon-sm shrink-0 text-[#202020]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <input type="time" x-model="pickupTime"
                       @click="$event.target.showPicker && $event.target.showPicker()"
                       class="w-full min-w-0 cursor-pointer bg-transparent text-body-sm font-semibold tracking-tight text-[#202020] focus:outline-none [&::-webkit-calendar-picker-indicator]:hidden">
            </div>
        </div>

        {{-- Flight / Bus Number (guest inputs). The label depends on the pickup
             location: "Flight Number" for the airport, "Bus Number" for the local
             bus services (Valgee, Nengee, Plateau Riders). --}}
        <div class="flex h-[73px] flex-col justify-center rounded-[14px] border-[0.5px] border-black/20 bg-[#f6f6f6] px-[19px] lg:min-w-0 lg:flex-1">
            <p class="text-label font-medium tracking-tight text-[#3c3c3c]"
               x-text="/airport/i.test(location) ? 'Flight Number' : 'Bus Number'">Flight Number</p>
            <input type="text" x-model="flightNumber"
                   :placeholder="/airport/i.test(location) ? 'e.g. LOS3782923' : 'e.g. BUS-1023'"
                   class="w-full bg-transparent text-body-sm font-semibold tracking-tight text-[#383838] placeholder:font-medium placeholder:text-[#7a7a7a] focus:outline-none">
        </div>

        {{-- Book --}}
        <button type="button" @if($bookAction) @click="{{ $bookAction }}" @endif
                class="flex h-[73px] shrink-0 items-center justify-center gap-[2px] rounded-[14px] bg-[#ba6d04] text-body-lg font-semibold tracking-tight text-white transition hover:bg-[#a35f03] lg:min-w-[110px] lg:max-w-[140px] lg:flex-[0.7]">
            Book
            <img loading="lazy" src="{{ asset('images/bookicon.png') }}" alt="" class="icon-md shrink-0 object-contain">
        </button>
    </div>

    {{-- Validation hint (shown when the guest taps Book without filling the details) --}}
    <p x-show="searchError" x-cloak x-text="searchError"
       class="mt-3 flex items-center gap-2 text-body-sm font-medium text-[#b91c1c]"></p>
</div>
