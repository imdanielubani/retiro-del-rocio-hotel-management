<?php

namespace App\Livewire\Admin\Cms;

use App\Models\SiteContent;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    public string $statusFilter = ''; // '' | published | draft

    public string $categoryFilter = ''; // '' | core | system

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function setCategory(string $c): void
    {
        $this->categoryFilter = $this->categoryFilter === $c ? '' : $c;
    }

    public function togglePublish(string $pageKey): void
    {
        if (! array_key_exists($pageKey, config('cms.pages'))) {
            return;
        }

        $statusKey = "page.{$pageKey}.status";
        $current = SiteContent::get($statusKey, 'published');
        $next = $current === 'published' ? 'draft' : 'published';
        SiteContent::put($statusKey, $next);

        $label = config("cms.pages.{$pageKey}.label");
        $this->dispatch('toast', type: 'success', message: $label.' is now '.$next.'.');
    }

    /** Build the per-page metadata used by the cards. */
    protected function pages(): array
    {
        $out = [];

        foreach (config('cms.pages') as $key => $page) {
            $fieldKeys = collect($page['fields'])->pluck('key')->all();

            $status = SiteContent::get("page.{$key}.status", 'published');
            $lastEdited = SiteContent::query()->whereIn('key', $fieldKeys)->max('updated_at');
            $everEdited = SiteContent::query()->whereIn('key', $fieldKeys)->exists();

            $out[$key] = [
                'key' => $key,
                'label' => $page['label'],
                'category' => $page['category'],
                'chips' => $page['chips'] ?? [],
                'status' => $status,
                'last_edited' => $lastEdited ? \Illuminate\Support\Carbon::parse($lastEdited)->format('M j, Y') : null,
                'incomplete' => ! $everEdited,
            ];
        }

        return $out;
    }

    public function render()
    {
        $pages = collect($this->pages());

        $stats = [
            'total' => $pages->count(),
            'published' => $pages->where('status', 'published')->count(),
            'draft' => $pages->where('status', 'draft')->count(),
            'attention' => $pages->where('incomplete', true)->count(),
        ];

        $filtered = $pages
            ->when($this->statusFilter, fn ($c) => $c->where('status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($c) => $c->where('category', $this->categoryFilter))
            ->when($this->search, fn ($c) => $c->filter(fn ($p) => str_contains(strtolower($p['label']), strtolower($this->search))
                || collect($p['chips'])->contains(fn ($chip) => str_contains(strtolower($chip), strtolower($this->search)))))
            ->values();

        return view('admin.cms.index', [
            'pages' => $filtered,
            'stats' => $stats,
            'hasFilters' => $this->search !== '' || $this->statusFilter !== '' || $this->categoryFilter !== '',
        ])->layout('components.admin.app', [
            'title' => 'Website CMS',
            'subtitle' => 'Manage the content shown across the website',
        ]);
    }
}
