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
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search plans…"
                       class="h-10 w-full rounded-lg border border-[#e5e7eb] bg-[#f9fafb] pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
            </div>
            <div class="flex items-center gap-1.5">
                @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
                    <button type="button" wire:click="setStatus(@js($key))"
                            @class([
                                'rounded-lg border px-4 py-1.5 text-[13px] font-medium transition',
                                'border-[#f38c00] bg-[#f38c00] font-semibold text-white' => $statusFilter === $key,
                                'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $statusFilter !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <p class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $plans->count() }}</span> {{ \Illuminate\Support\Str::plural('plan', $plans->count()) }}</p>
            <button type="button" wire:click="openCreate" class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#f38c00] px-5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Plan
            </button>
        </div>
    </div>

    {{-- ===== Plans list ===== --}}
    @if ($plans->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No plans yet</p>
            <p class="text-[13px] text-[#6b7280]">Add a fitness plan — it will appear on the website gym page.</p>
            <button type="button" wire:click="openCreate" class="mt-1 rounded-xl bg-[#f38c00] px-4 py-2 text-[13px] font-bold text-white hover:bg-[#dd7f00]">Add Plan</button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($plans as $p)
                <div wire:key="plan-{{ $p->id }}" class="relative flex flex-col rounded-2xl border bg-white p-6 {{ $p->is_featured ? 'border-[#f38c00] ring-1 ring-[#f38c00]/30' : 'border-[#e5e7eb]' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $p->name }}</h3>
                            @if ($p->is_featured)<span class="rounded-full bg-[#f3e8ff] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.4px] text-[#7c3aed]">Featured</span>@endif
                        </div>
                        @include('admin.gym.partials.plan-menu', ['p' => $p])
                    </div>
                    <div class="mt-2 flex items-end gap-1">
                        <span class="text-[26px] font-semibold text-[#1e1e1e]">{{ $p->priceLabel() }}</span>
                        <span class="pb-1 text-[12px] text-[#9ca3af]">/ {{ $p->periodShort() }}</span>
                    </div>
                    <p class="mt-2 text-[13px] leading-snug text-[#6b7280]">{{ $p->tagline }}</p>
                    <div class="mt-4 flex flex-1 flex-col gap-1.5 border-t border-[#f1f1ee] pt-4">
                        @foreach ($p->featureList() as $f)
                            <p class="flex items-start gap-2 text-[12.5px] text-[#374151]">
                                <svg class="mt-0.5 size-4 shrink-0 text-[#16a34a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                {{ $f }}
                            </p>
                        @endforeach
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-[#f1f1ee] pt-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $p->is_active ? 'bg-[#dcfce7] text-[#16a34a]' : 'bg-[#fee2e2] text-[#dc2626]' }}">
                            <span class="size-[5px] rounded-full {{ $p->is_active ? 'bg-[#22c55e]' : 'bg-[#dc2626]' }}"></span>{{ $p->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="text-[12px] text-[#9ca3af]">{{ $memberCounts[$p->id] ?? 0 }} active {{ \Illuminate\Support\Str::plural('member', $memberCounts[$p->id] ?? 0) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== Add / Edit modal ===== --}}
    @if ($showForm)
        <div class="fixed inset-0 z-[95] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative z-10 my-auto w-full max-w-[560px] overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-[#e5e7eb] px-6 py-4">
                    <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $editingId ? 'Edit Plan' : 'Add Plan' }}</h3>
                    <button type="button" wire:click="$set('showForm', false)" class="flex size-9 items-center justify-center rounded-lg text-[#6b7280] transition hover:bg-[#f1f1ee]"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Plan name</label>
                            <input type="text" wire:model="fName" placeholder="e.g. Standard Plan" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fName') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Price (₦)</label>
                            <input type="number" min="0" wire:model="fPrice" placeholder="70000" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                            @error('fPrice') <span class="text-[11px] text-[#dc2626]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[12px] font-semibold text-[#374151]">Billing period</label>
                            <select wire:model="fPeriod" class="h-11 rounded-xl border border-[#e5e7eb] px-3 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="semi-annually">Semi-annually</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">Tagline</label>
                            <input type="text" wire:model="fTagline" placeholder="Short line shown under the name" class="h-11 rounded-xl border border-[#e5e7eb] px-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15">
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-[12px] font-semibold text-[#374151]">What's included <span class="font-normal text-[#9ca3af]">(one per line)</span></label>
                            <textarea wire:model="fFeatures" rows="5" placeholder="Unlimited access to gym floor&#10;Group fitness classes&#10;Locker room & shower facilities" class="rounded-xl border border-[#e5e7eb] p-3.5 text-[14px] focus:border-[#f38c00] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/15"></textarea>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                            <input type="checkbox" wire:model="fFeatured" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                            <span class="text-[13px] text-[#374151]">Featured (highlighted)</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#e5e7eb] bg-[#f9fafb] px-3.5 py-3">
                            <input type="checkbox" wire:model="fActive" class="size-4 rounded border-[#d1d5db] text-[#f38c00] focus:ring-[#f38c00]/30">
                            <span class="text-[13px] text-[#374151]">Active (shown on website)</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-[#e5e7eb] px-6 py-4">
                    <button type="button" wire:click="$set('showForm', false)" class="rounded-xl border border-[#e5e7eb] px-5 py-2.5 text-[14px] font-medium text-[#374151] transition hover:bg-[#f9fafb]">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#f38c00] px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-[#dd7f00]">{{ $editingId ? 'Save changes' : 'Create plan' }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
