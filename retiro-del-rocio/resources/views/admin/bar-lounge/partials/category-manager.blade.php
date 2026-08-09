{{-- Bar & Lounge "Manage Categories" modal. $categories = drink MenuCategory collection. --}}
@if ($showCategoryManager)
    <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" wire:click="closeCategoryManager"></div>
        <div class="relative z-10 my-auto w-full max-w-[420px] overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                <h3 class="text-[17px] font-bold text-[#1e1e1e]">Bar & Lounge Categories</h3>
                <button type="button" wire:click="closeCategoryManager" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="flex flex-col gap-4 px-6 py-5">
                <form wire:submit="addCategory" class="flex items-start gap-2">
                    <div class="flex-1">
                        <input type="text" wire:model="newCategoryName" placeholder="e.g. Cocktails" class="h-11 w-full rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        @error('newCategoryName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#f38c00] px-4 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Add
                    </button>
                </form>

                <div class="flex flex-col divide-y divide-[#f1f1ee] rounded-xl border border-[#e5e7eb]">
                    @forelse ($categories as $c)
                        <div wire:key="bar-cat-{{ $c->id }}" class="flex items-center justify-between px-3.5 py-2.5">
                            <div>
                                <p class="text-[13px] font-medium text-[#1e1e1e]">{{ $c->name }}</p>
                                <p class="text-[11px] text-[#9ca3af]">{{ $c->itemCount() }} {{ \Illuminate\Support\Str::plural('drink', $c->itemCount()) }}</p>
                            </div>
                            <button type="button" wire:click="deleteCategory({{ $c->id }})" wire:confirm="Delete the \"{{ $c->name }}\" category?" class="flex size-8 items-center justify-center rounded-lg text-[#9ca3af] transition hover:bg-[#fef2f2] hover:text-[#dc2626]">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                            </button>
                        </div>
                    @empty
                        <p class="px-3.5 py-4 text-center text-[13px] text-[#9ca3af]">No categories yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="flex justify-end border-t border-[#e5e7eb] px-6 py-4">
                <button type="button" wire:click="closeCategoryManager" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Done</button>
            </div>
        </div>
    </div>
@endif
