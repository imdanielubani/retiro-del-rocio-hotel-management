<div class="flex flex-col gap-4">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="flex flex-col gap-2 rounded-xl border border-[#e5e7eb] border-l-4 bg-white px-6 py-5" style="border-left-color: {{ $stat['accent'] }}">
                <p class="text-[11px] uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-medium leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px]" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3.5 xl:flex-row xl:items-center xl:gap-3">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:gap-2.5">
            <div class="relative w-full sm:w-[240px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search movies…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach (['' => 'All', 'now_showing' => 'Now Showing', 'coming_soon' => 'Coming Soon'] as $key => $label)
                    <button type="button" wire:click="setClass(@js($key))"
                            @class([
                                'rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $classFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $classFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $movies->count() }}</span> {{ \Illuminate\Support\Str::plural('movie', $movies->count()) }}</p>
            <button type="button" wire:click="openCreate" class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Movie
            </button>
        </div>
    </div>

    {{-- ===== Movies grid ===== --}}
    @if ($movies->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No movies yet</p>
            <p class="text-[13px] text-[#6b7280]">Add a movie — it will appear on the website cinema page.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Movie</button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($movies as $m)
                <div wire:key="movie-{{ $m->id }}" class="relative flex gap-4 rounded-2xl border bg-white p-4 {{ $m->is_featured ? 'border-[#f38c00] ring-1 ring-[#f38c00]/30' : 'border-[#e5e7eb]' }}">
                    <div class="relative h-[150px] w-[104px] shrink-0 overflow-hidden rounded-xl bg-[#f1f1ee]">
                        <img src="{{ $m->posterUrl() }}" alt="{{ $m->title }}" class="h-full w-full object-cover">
                        @if ($m->rating)<span class="absolute left-1.5 top-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $m->rating }}</span>@endif
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-[15px] font-bold text-[#1e1e1e]">{{ $m->title }}</h3>
                                <p class="truncate text-[12px] text-[#6b7280]">{{ $m->genre ?: '—' }}@if ($m->duration) · {{ $m->duration }}@endif</p>
                            </div>
                            @include('admin.cinema.partials.movie-menu', ['m' => $m])
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.4px] {{ $m->classification === 'coming_soon' ? 'bg-[#f3e8ff] text-[#7c3aed]' : 'bg-[#dcfce7] text-[#16a34a]' }}">{{ $m->classificationLabel() }}</span>
                            @if ($m->is_featured)<span class="inline-flex rounded-full bg-[#fff3e0] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.4px] text-[#b45309]">Hero</span>@endif
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $m->is_active ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                                <span class="size-[5px] rounded-full {{ $m->is_active ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ $m->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <div class="mt-auto flex items-end justify-between border-t border-[#f1f1ee] pt-2.5">
                            <div class="text-[12px] text-[#6b7280]">
                                <p>Private room <span class="font-semibold text-[#1e1e1e]">{{ $m->roomPriceLabel() }}</span></p>
                                <p class="text-[11px]">{{ \App\Models\Movie::SEATS_PER_ROOM }} seats · {{ count(\App\Models\Movie::ROOMS) }} rooms / show</p>
                            </div>
                            <span class="text-[11px] text-[#9ca3af]">{{ $bookingCounts[$m->id] ?? 0 }} {{ \Illuminate\Support\Str::plural('booking', $bookingCounts[$m->id] ?? 0) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 my-auto w-full max-w-[640px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Movie' : 'Add Movie' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="max-h-[72vh] overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Movie title</label>
                            <input type="text" wire:model="fTitle" placeholder="e.g. Dune: Part Two" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fTitle') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Genre</label>
                            <input type="text" wire:model="fGenre" placeholder="Action, Sci-Fi" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Duration</label>
                            <input type="text" wire:model="fDuration" placeholder="2h 46m" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Rating</label>
                            <input type="text" wire:model="fRating" placeholder="PG-13" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Classification</label>
                            <select wire:model="fClassification" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="now_showing">Now Showing</option>
                                <option value="coming_soon">Coming Soon</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Private room price (₦) <span class="font-normal text-[#9ca3af]">— charged per whole {{ \App\Models\Movie::SEATS_PER_ROOM }}-seat room</span></label>
                            <input type="number" min="0" wire:model="fRoomPrice" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fRoomPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Trailer URL <span class="font-normal text-[#9ca3af]">(YouTube, optional)</span></label>
                            <input type="url" wire:model="fTrailer" placeholder="https://youtube.com/watch?v=…" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fTrailer') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Synopsis</label>
                            <textarea wire:model="fSynopsis" rows="3" placeholder="Short description shown on the movie page" class="rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Showtimes <span class="font-normal text-[#9ca3af]">(one per line)</span></label>
                            <textarea wire:model="fShowtimes" rows="4" placeholder="10:30 AM&#10;1:00 PM&#10;4:00 PM" class="rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        </div>

                        {{-- Poster --}}
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Poster</label>
                            <div class="flex items-center gap-3">
                                <div class="h-[96px] w-[68px] shrink-0 overflow-hidden rounded-lg bg-[#f1f1ee]">
                                    @if ($fPoster)
                                        <img src="{{ $fPoster->temporaryUrl() }}" class="h-full w-full object-cover">
                                    @elseif ($fPosterPath)
                                        <img src="{{ \App\Models\SiteContent::imageUrl($fPosterPath) }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                                <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-[#d6d9d2] bg-[#f9fafb] px-3 py-3 text-center text-[12px] text-[#6b7280] transition hover:border-[#f38c00]">
                                    <input type="file" wire:model="fPoster" accept="image/*" class="hidden">
                                    <span wire:loading.remove wire:target="fPoster">Upload poster</span>
                                    <span wire:loading wire:target="fPoster">Uploading…</span>
                                </label>
                            </div>
                            @error('fPoster') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>

                        {{-- Backdrop --}}
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Backdrop <span class="font-normal text-[#9ca3af]">(optional)</span></label>
                            <div class="flex items-center gap-3">
                                <div class="h-[96px] w-[68px] shrink-0 overflow-hidden rounded-lg bg-[#f1f1ee]">
                                    @if ($fBackdrop)
                                        <img src="{{ $fBackdrop->temporaryUrl() }}" class="h-full w-full object-cover">
                                    @elseif ($fBackdropPath)
                                        <img src="{{ \App\Models\SiteContent::imageUrl($fBackdropPath) }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                                <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-[#d6d9d2] bg-[#f9fafb] px-3 py-3 text-center text-[12px] text-[#6b7280] transition hover:border-[#f38c00]">
                                    <input type="file" wire:model="fBackdrop" accept="image/*" class="hidden">
                                    <span wire:loading.remove wire:target="fBackdrop">Upload backdrop</span>
                                    <span wire:loading wire:target="fBackdrop">Uploading…</span>
                                </label>
                            </div>
                            @error('fBackdrop') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                            <input type="checkbox" wire:model="fFeatured" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                            <span class="text-[13px] text-[#374151]">Feature in hero carousel</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                            <input type="checkbox" wire:model="fActive" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                            <span class="text-[13px] text-[#374151]">Active (shown on website)</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="flex items-center gap-2 rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save changes' : 'Add movie' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
