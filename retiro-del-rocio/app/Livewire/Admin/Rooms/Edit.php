<?php

namespace App\Livewire\Admin\Rooms;

use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    /** Master amenity list (icon keys match the public room views). */
    public const AMENITIES = [
        'fitness' => 'Fitness Lounge',
        'bed' => '2 Beds',
        'wifi' => 'Wifi',
        'pool' => 'Pool',
        'restaurant' => 'Restaurant',
        'parking' => 'Parking',
        'breakfast' => 'Complimentary Breakfast',
    ];

    /** "Additional" feature list (icon keys match the public room views). */
    public const ADDITIONAL = [
        'self_checkin' => 'Self-check-in',
        'airport' => 'Vehicle pickup',
        'chef' => 'Private chef',
        'housekeeping' => '24/7 House-keeping',
    ];

    public ?int $roomId = null;

    public string $name = '';

    public string $type = '';

    public int $price = 0;

    public int $beds = 2;

    public int $guests = 2;

    public int $sqft = 45;

    public int $bathrooms = 1;

    public string $short_description = '';

    public string $description = '';

    public string $cancellation_policy = '';

    public bool $is_published = true;

    /** @var array<int,string> selected amenity icon keys */
    public array $amenities = [];

    /** @var array<int,string> selected "additional" icon keys */
    public array $additional = [];

    // Featured image
    public $featured;                 // new upload (TemporaryUploadedFile)

    public ?string $existingFeatured = null;

    // Gallery
    public array $newGallery = [];    // new uploads

    public array $existingGallery = []; // stored paths

    // Room numbers (units) — inputs
    public string $unitStart = '';

    public int $unitQty = 1;

    public string $unitSingle = '';

    public function mount(?Room $room = null): void
    {
        if ($room && $room->exists) {
            $this->roomId = $room->id;
            $this->name = $room->name;
            $this->type = (string) $room->type;
            $this->price = (int) $room->price;
            $this->beds = (int) $room->beds;
            $this->guests = (int) $room->guests;
            $this->sqft = (int) $room->sqft;
            $this->bathrooms = (int) $room->bathrooms;
            $this->short_description = (string) $room->short_description;
            $this->description = (string) $room->description;
            $this->cancellation_policy = (string) $room->cancellation_policy;
            $this->is_published = (bool) $room->is_published;
            $this->amenities = collect($room->amenities ?? [])->pluck('icon')->all();
            $this->additional = collect($room->additional ?? [])->pluck('icon')->all();
            $this->existingFeatured = $room->featured_image;
            $this->existingGallery = $room->gallery ?? [];
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'beds' => ['required', 'integer', 'min:0', 'max:50'],
            'guests' => ['required', 'integer', 'min:0', 'max:50'],
            'sqft' => ['required', 'integer', 'min:0'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cancellation_policy' => ['nullable', 'string', 'max:2000'],
            'amenities' => ['array'],
            'additional' => ['array'],
            'featured' => ['nullable', 'image', 'max:5120'],
            'newGallery.*' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function removeExistingFeatured(): void
    {
        $this->existingFeatured = null;
    }

    public function removeNewGallery(int $i): void
    {
        unset($this->newGallery[$i]);
        $this->newGallery = array_values($this->newGallery);
    }

    public function removeExistingGallery(int $i): void
    {
        unset($this->existingGallery[$i]);
        $this->existingGallery = array_values($this->existingGallery);
    }

    // ---- Room numbers (units) ----
    public function addUnitRange(): void
    {
        if (! $this->roomId) {
            return;
        }
        $this->validate([
            'unitStart' => ['required', 'string', 'max:20'],
            'unitQty' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $created = 0;
        // Numeric start → sequential numbers; otherwise just create the single label.
        if (is_numeric($this->unitStart)) {
            $start = (int) $this->unitStart;
            $width = strlen(ltrim($this->unitStart)); // preserve zero-padding width
            for ($i = 0; $i < $this->unitQty; $i++) {
                $num = str_pad((string) ($start + $i), $width, '0', STR_PAD_LEFT);
                $created += $this->createUnit($num);
            }
        } else {
            $created += $this->createUnit(trim($this->unitStart));
        }

        $this->reset(['unitStart', 'unitQty']);
        $this->unitQty = 1;
        $this->dispatch('toast', type: 'success', message: $created.' room number'.($created === 1 ? '' : 's').' added.');
    }

    public function addUnit(): void
    {
        if (! $this->roomId) {
            return;
        }
        $this->validate(['unitSingle' => ['required', 'string', 'max:20']]);
        $n = $this->createUnit(trim($this->unitSingle));
        $this->reset('unitSingle');
        $this->dispatch('toast', type: $n ? 'success' : 'error', message: $n ? 'Room number added.' : 'That number already exists.');
    }

    protected function createUnit(string $number): int
    {
        if ($number === '' || RoomUnit::where('room_id', $this->roomId)->where('number', $number)->exists()) {
            return 0;
        }
        RoomUnit::create(['room_id' => $this->roomId, 'number' => $number, 'status' => 'available']);

        return 1;
    }

    public function removeUnit(int $id): void
    {
        $unit = RoomUnit::where('room_id', $this->roomId)->find($id);
        if (! $unit) {
            return;
        }
        if ($unit->status === 'occupied') {
            $this->dispatch('toast', type: 'error', message: 'Room '.$unit->number.' is occupied — check the guest out first.');

            return;
        }
        $unit->delete();
        $this->dispatch('toast', type: 'success', message: 'Room number removed.');
    }

    public function save()
    {
        $data = $this->validate();

        $room = $this->roomId ? Room::findOrFail($this->roomId) : new Room;

        // Featured
        $featuredPath = $this->existingFeatured;
        if ($this->featured) {
            $featuredPath = $this->featured->store('rooms', 'public');
            \App\Support\ImageOptimizer::optimize($featuredPath);
        }

        // Gallery: keep retained existing + append new uploads
        $gallery = $this->existingGallery;
        foreach ($this->newGallery as $file) {
            $path = $file->store('rooms', 'public');
            \App\Support\ImageOptimizer::optimize($path);
            $gallery[] = $path;
        }

        // Amenities → [{label, icon}] preserving the master order
        $amenities = [];
        foreach (self::AMENITIES as $icon => $label) {
            if (in_array($icon, $this->amenities, true)) {
                $amenities[] = ['label' => $label, 'icon' => $icon];
            }
        }

        // Additional features → [{label, icon}] preserving the master order
        $additional = [];
        foreach (self::ADDITIONAL as $icon => $label) {
            if (in_array($icon, $this->additional, true)) {
                $additional[] = ['label' => $label, 'icon' => $icon];
            }
        }

        $room->fill([
            'name' => $this->name,
            'slug' => $room->slug ?: Str::slug($this->name).'-'.Str::lower(Str::random(4)),
            'type' => $this->type,
            'price' => $this->price,
            'beds' => $this->beds,
            'guests' => $this->guests,
            'sqft' => $this->sqft,
            'bathrooms' => $this->bathrooms,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'cancellation_policy' => $this->cancellation_policy,
            'amenities' => $amenities,
            'additional' => $additional,
            'featured_image' => $featuredPath,
            'gallery' => array_values($gallery),
            'is_published' => $this->is_published,
            'sort_order' => $room->sort_order ?? (Room::max('sort_order') + 1),
        ])->save();

        session()->flash('toast', [
            'type' => 'success',
            'message' => $room->name.' was '.($this->roomId ? 'updated' : 'created').'.',
        ]);

        return $this->redirect(route('admin.rooms.index'), navigate: true);
    }

    public function render()
    {
        // Existing categories to choose from (plus the current one, so editing never loses it).
        $categoryOptions = Room::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->push($this->type)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $units = $this->roomId
            ? RoomUnit::where('room_id', $this->roomId)->orderByRaw('LENGTH(number), number')->get()
            : collect();

        return view('admin.rooms.edit', [
            'amenityOptions' => self::AMENITIES,
            'additionalOptions' => self::ADDITIONAL,
            'categoryOptions' => $categoryOptions,
            'units' => $units,
        ])->layout('components.admin.app', [
            'title' => $this->roomId ? 'Edit Room' : 'Add Room',
            'subtitle' => $this->roomId ? 'Update an apartment listing' : 'Create a new apartment listing',
        ]);
    }
}
