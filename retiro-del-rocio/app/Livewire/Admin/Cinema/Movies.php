<?php

namespace App\Livewire\Admin\Cinema;

use App\Models\CinemaBooking;
use App\Models\Movie;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Movies extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $classFilter = ''; // '' | now_showing | coming_soon

    public string $statusFilter = ''; // '' | active | inactive

    /* ----- Add / Edit modal ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fTitle = '';

    public string $fGenre = '';

    public string $fDuration = '';

    public string $fRating = '';

    public string $fSynopsis = '';

    public string $fTrailer = '';

    public $fRoomPrice = 40000;

    public string $fClassification = 'now_showing';

    public string $fShowtimes = ''; // one per line

    public bool $fFeatured = false;

    public bool $fActive = true;

    public $fPoster;          // new upload

    public $fBackdrop;        // new upload

    public ?string $fPosterPath = null;   // existing path

    public ?string $fBackdropPath = null; // existing path

    protected $validationAttributes = [
        'fTitle' => 'title', 'fRoomPrice' => 'room price',
        'fPoster' => 'poster', 'fBackdrop' => 'backdrop',
    ];

    public function updatedSearch(): void {}

    public function setClass(string $s): void
    {
        $this->classFilter = $this->classFilter === $s ? '' : $s;
    }

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset([
            'editingId', 'fTitle', 'fGenre', 'fDuration', 'fRating', 'fSynopsis', 'fTrailer',
            'fRoomPrice', 'fClassification', 'fShowtimes', 'fFeatured', 'fActive',
            'fPoster', 'fBackdrop', 'fPosterPath', 'fBackdropPath',
        ]);
        $this->fRoomPrice = 40000;
        $this->fClassification = 'now_showing';
        $this->fActive = true;
        $this->fShowtimes = "10:30 AM\n1:00 PM\n4:00 PM\n7:00 PM\n10:00 PM";
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $m = Movie::findOrFail($id);
        $this->editingId = $m->id;
        $this->fTitle = $m->title;
        $this->fGenre = (string) $m->genre;
        $this->fDuration = (string) $m->duration;
        $this->fRating = (string) $m->rating;
        $this->fSynopsis = (string) $m->synopsis;
        $this->fTrailer = (string) $m->trailer_url;
        $this->fRoomPrice = $m->room_price;
        $this->fClassification = $m->classification ?: 'now_showing';
        $this->fShowtimes = implode("\n", $m->showtimeList());
        $this->fFeatured = $m->is_featured;
        $this->fActive = $m->is_active;
        $this->fPosterPath = $m->poster_image;
        $this->fBackdropPath = $m->backdrop_image;
        $this->reset(['fPoster', 'fBackdrop']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fTitle' => ['required', 'string', 'max:160'],
            'fGenre' => ['nullable', 'string', 'max:120'],
            'fDuration' => ['nullable', 'string', 'max:40'],
            'fRating' => ['nullable', 'string', 'max:20'],
            'fSynopsis' => ['nullable', 'string', 'max:3000'],
            'fTrailer' => ['nullable', 'url', 'max:255'],
            'fRoomPrice' => ['required', 'integer', 'min:0'],
            'fClassification' => ['required', 'in:now_showing,coming_soon'],
            'fShowtimes' => ['nullable', 'string', 'max:2000'],
            'fFeatured' => ['boolean'],
            'fActive' => ['boolean'],
            'fPoster' => ['nullable', 'image', 'max:4096'],
            'fBackdrop' => ['nullable', 'image', 'max:6144'],
        ]);

        $showtimes = collect(preg_split('/\r\n|\r|\n/', $this->fShowtimes))
            ->map(fn ($l) => trim($l))->filter()->values()->all();

        $posterPath = $this->fPoster
            ? $this->fPoster->store('movies', 'public')
            : $this->fPosterPath;
        if ($this->fPoster) {
            \App\Support\ImageOptimizer::optimize($posterPath);
        }
        $backdropPath = $this->fBackdrop
            ? $this->fBackdrop->store('movies', 'public')
            : $this->fBackdropPath;
        if ($this->fBackdrop) {
            \App\Support\ImageOptimizer::optimize($backdropPath);
        }

        $payload = [
            'title' => $data['fTitle'],
            'genre' => $data['fGenre'] ?: null,
            'duration' => $data['fDuration'] ?: null,
            'rating' => $data['fRating'] ?: null,
            'synopsis' => $data['fSynopsis'] ?: null,
            'trailer_url' => $data['fTrailer'] ?: null,
            'room_price' => (int) $data['fRoomPrice'],
            'classification' => $data['fClassification'],
            'showtimes' => $showtimes,
            'poster_image' => $posterPath,
            'backdrop_image' => $backdropPath,
            'is_featured' => $this->fFeatured,
            'is_active' => $this->fActive,
        ];

        if ($this->editingId) {
            $movie = Movie::findOrFail($this->editingId);
            $movie->update($payload + ['slug' => $movie->slug]);
        } else {
            $payload['slug'] = $this->uniqueSlug($data['fTitle']);
            $payload['sort_order'] = (int) Movie::max('sort_order') + 1;
            $movie = Movie::create($payload);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Movie '.($this->editingId ? 'updated' : 'added').'.');
    }

    public function toggleActive(int $id): void
    {
        $m = Movie::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
        $this->dispatch('toast', type: 'success', message: $m->title.' is now '.($m->is_active ? 'active' : 'hidden').'.');
    }

    public function toggleFeatured(int $id): void
    {
        $m = Movie::findOrFail($id);
        $m->update(['is_featured' => ! $m->is_featured]);
        $this->dispatch('toast', type: 'success', message: $m->title.($m->is_featured ? ' added to the hero.' : ' removed from the hero.'));
    }

    public function delete(int $id): void
    {
        Movie::where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Movie deleted.');
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'movie';
        $slug = $base;
        $i = 1;
        while (Movie::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    public function render()
    {
        $base = Movie::query();
        $total = (clone $base)->count();
        $nowShowing = (clone $base)->where('classification', 'now_showing')->where('is_active', true)->count();
        $comingSoon = (clone $base)->where('classification', 'coming_soon')->count();
        $bookings = CinemaBooking::where('status', '!=', 'cancelled')->count();

        $stats = [
            ['label' => 'Total Movies', 'value' => $total, 'sub' => 'In the catalogue', 'accent' => '#f38c00'],
            ['label' => 'Now Showing', 'value' => $nowShowing, 'sub' => 'Live on the website', 'accent' => '#16a34a'],
            ['label' => 'Coming Soon', 'value' => $comingSoon, 'sub' => 'Upcoming releases', 'accent' => '#7c3aed'],
            ['label' => 'Tickets Sold', 'value' => $bookings, 'sub' => 'All bookings', 'accent' => '#0891b2'],
        ];

        $bookingCounts = CinemaBooking::selectRaw('movie_id, count(*) as c')
            ->where('status', '!=', 'cancelled')->groupBy('movie_id')->pluck('c', 'movie_id');

        $movies = Movie::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('genre', 'like', "%{$this->search}%")))
            ->when($this->classFilter, fn ($q) => $q->where('classification', $this->classFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->ordered()->get();

        return view('admin.cinema.movies', [
            'movies' => $movies,
            'stats' => $stats,
            'bookingCounts' => $bookingCounts,
        ])->layout('components.admin.app', [
            'title' => 'Cinema Movies',
            'subtitle' => 'Films shown on the website cinema page',
        ]);
    }
}
