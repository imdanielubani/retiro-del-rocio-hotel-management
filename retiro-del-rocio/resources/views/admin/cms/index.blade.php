@php
    $statCards = [
        ['label' => 'Total Pages', 'value' => $stats['total'], 'sub' => 'Website pages', 'accent' => '#f38c00'],
        ['label' => 'Published', 'value' => $stats['published'], 'sub' => 'Live on website', 'accent' => '#16a34a'],
        ['label' => 'Draft', 'value' => $stats['draft'], 'sub' => 'Not yet published', 'accent' => '#d97706'],
        ['label' => 'Needs Attention', 'value' => $stats['attention'], 'sub' => 'Using default content', 'accent' => '#dc2626'],
    ];
@endphp

<div class="flex flex-col gap-4" x-data="{ view: 'grid' }">
    {{-- ===== Stat cards ===== --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statCards as $stat)
            <div class="flex flex-col gap-1.5 rounded-2xl border border-[#e5e7eb] border-t-4 bg-white px-6 py-5"
                 style="border-top-color: {{ $stat['accent'] }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.5px] text-[#6b7280]">{{ $stat['label'] }}</p>
                <p class="text-[clamp(22px,2vw,28px)] font-semibold leading-tight text-[#1e1e1e]">{{ $stat['value'] }}</p>
                <p class="text-[11px] font-medium" style="color: {{ $stat['accent'] }}">{{ $stat['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Toolbar (search + filters + view toggle + count) ===== --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#e5e7eb] bg-white p-3 xl:flex-row xl:items-center xl:justify-between xl:gap-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2 xl:flex-1">
        {{-- Search --}}
        <div class="relative w-full sm:w-[210px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-[#9ca3af]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3" stroke-linecap="round"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search pages or sections…"
                   class="h-10 w-full rounded-full border border-[#e5e7eb] bg-white pl-10 pr-4 text-[13px] text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-none focus:ring-2 focus:ring-[#f38c00]/20">
        </div>

        {{-- Status filter --}}
        <div class="no-scrollbar flex flex-nowrap items-center gap-1.5 overflow-x-auto">
            @foreach (['' => 'All Pages', 'published' => 'Published', 'draft' => 'Draft'] as $key => $label)
                <button type="button" wire:click="$set('statusFilter', @js($key))"
                        @class([
                            'shrink-0 whitespace-nowrap rounded-full px-4 py-2 text-[13px] font-medium transition',
                            'bg-[#f38c00] text-white' => $statusFilter === $key,
                            'text-[#6b7280] hover:bg-[#f3f4f6]' => $statusFilter !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        {{-- Category filter --}}
        <div class="no-scrollbar flex flex-nowrap items-center gap-1.5 overflow-x-auto">
            @foreach (['' => 'All Categories', 'core' => 'Core Pages', 'service' => 'Service Pages', 'system' => 'System Pages'] as $key => $label)
                <button type="button" wire:click="$set('categoryFilter', @js($key))"
                        @class([
                            'shrink-0 whitespace-nowrap rounded-full border px-4 py-2 text-[13px] font-medium transition',
                            'border-[#f38c00] bg-[#fff7ed] text-[#f38c00]' => $categoryFilter === $key,
                            'border-[#e5e7eb] bg-white text-[#6b7280] hover:bg-[#f9fafb]' => $categoryFilter !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
      </div>

        {{-- View toggle + page count --}}
        <div class="flex items-center justify-between gap-3 xl:shrink-0">
            <div class="flex items-center gap-1 rounded-xl border border-[#e5e7eb] p-1">
                <button type="button" @click="view = 'grid'" title="Grid view"
                        :class="view === 'grid' ? 'bg-[#f38c00] text-white' : 'text-[#9ca3af] hover:bg-[#f3f4f6]'"
                        class="flex size-8 items-center justify-center rounded-lg transition">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                </button>
                <button type="button" @click="view = 'list'" title="List view"
                        :class="view === 'list' ? 'bg-[#f38c00] text-white' : 'text-[#9ca3af] hover:bg-[#f3f4f6]'"
                        class="flex size-8 items-center justify-center rounded-lg transition">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </button>
            </div>
            <span class="shrink-0 whitespace-nowrap text-[13px] text-[#6b7280]"><span class="font-semibold text-[#1e1e1e]">{{ $pages->count() }}</span> {{ \Illuminate\Support\Str::plural('page', $pages->count()) }}</span>
        </div>
    </div>

    {{-- ===== Page cards ===== --}}
    @if ($pages->isEmpty())
        <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-[#d6d9d2] bg-white py-16 text-center">
            <p class="text-[15px] font-semibold text-[#1e1e1e]">No pages match your filters</p>
            <p class="text-[13px] text-[#6b7280]">Try a different search or category.</p>
        </div>
    @else
        <div :class="view === 'grid' ? 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3' : 'flex flex-col gap-3'">
            @foreach ($pages as $page)
                @php
                    $isPublished = $page['status'] === 'published';
                    $catColor = match ($page['category']) {
                        'core' => '#f38c00',
                        'service' => '#16a34a',
                        'system' => '#3b82f6',
                        default => '#6b7280',
                    };
                @endphp
                <div class="flex flex-col gap-4 overflow-hidden rounded-2xl border border-[#e5e7eb] border-t-[3px] bg-white p-5"
                     style="border-top-color: {{ $isPublished ? '#16a34a' : '#d97706' }}" wire:key="cms-{{ $page['key'] }}">
                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-[11px] font-bold uppercase tracking-[1px]" style="color: {{ $catColor }}">{{ $page['category'] }}</span>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                            'bg-[#dcfce7] text-[#16a34a]' => $isPublished,
                            'bg-[#fef3c7] text-[#d97706]' => ! $isPublished,
                        ])>{{ $isPublished ? 'Published' : 'Draft' }}</span>
                    </div>

                    {{-- Title + chips --}}
                    <div class="flex flex-col gap-2">
                        <h3 class="text-[17px] font-bold text-[#1e1e1e]">{{ $page['label'] }}</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($page['chips'] as $chip)
                                <span class="rounded-md bg-[#f3f4f6] px-2 py-0.5 text-[11px] font-medium text-[#6b7280]">{{ $chip }}</span>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[12px] text-[#9ca3af]">
                            @if ($page['last_edited'])
                                Last edited: <span class="font-medium text-[#6b7280]">{{ $page['last_edited'] }}</span>
                            @else
                                Using default content
                            @endif
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-1 flex items-center gap-2 border-t border-[#f1f1ee] pt-4">
                        <a href="{{ route('admin.cms.edit', $page['key']) }}" wire:navigate
                           class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#f38c00] px-4 py-2.5 text-[13px] font-bold text-white transition hover:bg-[#dd7f00]">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                            Edit
                        </a>
                        <button type="button" wire:click="togglePublish('{{ $page['key'] }}')"
                                class="flex flex-1 items-center justify-center rounded-xl border px-4 py-2.5 text-[13px] font-medium transition {{ $isPublished ? 'border-[#fde2e2] bg-[#fef2f2] text-[#dc2626] hover:bg-[#fee2e2]' : 'border-[#dcfce7] bg-[#f0fdf4] text-[#16a34a] hover:bg-[#dcfce7]' }}">
                            {{ $isPublished ? 'Unpublish' : 'Publish' }}
                        </button>
                        <a href="{{ config('cms.pages.'.$page['key'].'.preview') }}" target="_blank" rel="noopener"
                           class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-[#e5e7eb] text-[#6b7280] transition hover:bg-[#f9fafb]" title="Preview on site">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
