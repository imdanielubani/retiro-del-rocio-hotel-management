<?php

namespace App\Livewire\Admin\Gym;

use App\Models\GymMembership;
use App\Models\GymPlan;
use Illuminate\Support\Str;
use Livewire\Component;

class Plans extends Component
{
    public string $search = '';

    public string $statusFilter = ''; // '' | active | inactive

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public $fPrice = '';

    public string $fPeriod = 'monthly';

    public string $fTagline = '';

    public string $fFeatures = ''; // one per line

    public bool $fFeatured = false;

    public bool $fActive = true;

    protected $validationAttributes = [
        'fName' => 'name', 'fPrice' => 'price', 'fFeatures' => 'features',
    ];

    public function updatedSearch(): void {}

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fPrice', 'fPeriod', 'fTagline', 'fFeatures', 'fFeatured', 'fActive']);
        $this->fPeriod = 'monthly';
        $this->fActive = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = GymPlan::findOrFail($id);
        $this->editingId = $p->id;
        $this->fName = $p->name;
        $this->fPrice = $p->price;
        $this->fPeriod = $p->period;
        $this->fTagline = (string) $p->tagline;
        $this->fFeatures = implode("\n", $p->featureList());
        $this->fFeatured = $p->is_featured;
        $this->fActive = $p->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fPrice' => ['required', 'integer', 'min:0'],
            'fPeriod' => ['required', 'string', 'max:20'],
            'fTagline' => ['nullable', 'string', 'max:255'],
            'fFeatures' => ['nullable', 'string', 'max:2000'],
            'fFeatured' => ['boolean'],
            'fActive' => ['boolean'],
        ]);

        $features = collect(preg_split('/\r\n|\r|\n/', $this->fFeatures))
            ->map(fn ($l) => trim($l))->filter()->values()->all();

        $payload = [
            'name' => $data['fName'],
            'price' => (int) $data['fPrice'],
            'period' => $data['fPeriod'],
            'tagline' => $data['fTagline'] ?: null,
            'features' => $features,
            'is_featured' => $this->fFeatured,
            'is_active' => $this->fActive,
        ];

        if ($this->editingId) {
            $plan = GymPlan::findOrFail($this->editingId);
            $plan->update($payload + ['slug' => $plan->slug]);
        } else {
            $payload['slug'] = $this->uniqueSlug($data['fName']);
            $payload['sort_order'] = (int) GymPlan::max('sort_order') + 1;
            $plan = GymPlan::create($payload);
        }

        // Keep a single highlighted plan.
        if ($this->fFeatured) {
            GymPlan::where('id', '!=', $plan->id)->update(['is_featured' => false]);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Plan '.($this->editingId ? 'updated' : 'created').'.');
    }

    public function toggleActive(int $id): void
    {
        $p = GymPlan::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        $this->dispatch('toast', type: 'success', message: $p->name.' is now '.($p->is_active ? 'active' : 'inactive').'.');
    }

    public function delete(int $id): void
    {
        GymPlan::where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Plan deleted.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 1;
        while (GymPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function render()
    {
        $base = GymPlan::query();
        $total = (clone $base)->count();
        $active = (clone $base)->where('is_active', true)->count();
        $featured = (clone $base)->where('is_featured', true)->count();
        $members = GymMembership::where('status', 'active')->count();

        $stats = [
            ['label' => 'Total Plans', 'value' => $total, 'sub' => 'Membership tiers', 'accent' => '#f38c00'],
            ['label' => 'Active', 'value' => $active, 'sub' => 'Currently bookable', 'accent' => '#16a34a'],
            ['label' => 'Featured', 'value' => $featured, 'sub' => 'Highlighted plan', 'accent' => '#7c3aed'],
            ['label' => 'Active Members', 'value' => $members, 'sub' => 'On a plan', 'accent' => '#0891b2'],
        ];

        $memberCounts = GymMembership::selectRaw('gym_plan_id, count(*) as c')
            ->where('status', 'active')->groupBy('gym_plan_id')->pluck('c', 'gym_plan_id');

        $plans = GymPlan::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()->get();

        return view('admin.gym.plans', [
            'plans' => $plans,
            'stats' => $stats,
            'memberCounts' => $memberCounts,
        ])->layout('components.admin.app', [
            'title' => 'Gym Plans',
            'subtitle' => 'Fitness membership tiers shown on the gym page',
        ]);
    }
}
