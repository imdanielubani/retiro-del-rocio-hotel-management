{{-- Row actions for a room's housekeeping status ($unit). Mirrors the apartment-bookings action menu. --}}
<div x-data="{ open: false }" class="relative flex justify-end">
    <button type="button" @click="open = !open"
            :class="open ? 'border-[#f38c00] text-[#f38c00]' : 'border-[#e5e7eb] text-[#6b7280]'"
            class="flex size-8 items-center justify-center rounded-lg border bg-white transition hover:bg-[#f9fafb]" aria-label="Actions">
        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
         class="absolute right-0 top-9 z-50 w-[220px] overflow-hidden rounded-xl border border-[#e5e7eb] bg-white py-1.5 shadow-xl">
        <div class="border-b border-[#f1f1ee] px-4 pb-2 pt-1 text-right">
            <p class="text-[11px] text-[#9ca3af]">Room {{ $unit->number }}</p>
            <p class="truncate text-[13px] font-bold text-[#1e1e1e]">{{ $unit->housekeepingStatusLabel() }}</p>
        </div>

        @foreach (\App\Models\RoomUnit::HOUSEKEEPING_STATUSES as $status)
            @continue($status === $unit->housekeeping_status)
            <button type="button" @click="open = false" wire:click="setRoomStatus({{ $unit->id }}, @js($status))"
                    class="flex w-full items-center gap-2.5 border-t border-[#f1f1ee] px-4 py-2 text-left text-[13px] text-[#374151] transition first:border-t-0 hover:bg-[#f9fafb]">
                <span class="size-[7px] rounded-full" style="background: {{ match ($status) {
                    'dirty' => '#d97706', 'preparing' => '#2563eb', 'inspected' => '#2563eb', 'out_of_order' => '#dc2626', default => '#16a34a',
                } }}"></span>
                Mark {{ match ($status) {
                    'dirty' => 'Dirty', 'preparing' => 'Preparing', 'inspected' => 'Inspected', 'out_of_order' => 'Out of Order', default => 'Clean',
                } }}
            </button>
        @endforeach
    </div>
</div>
