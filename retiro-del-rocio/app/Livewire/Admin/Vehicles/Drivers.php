<?php

namespace App\Livewire\Admin\Vehicles;

use App\Models\Driver;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The pickup-driver roster. Admins add and manage the drivers that reception and
 * the admin Vehicle Pickup bookings assign to guest arrivals.
 *
 * The search + filter section mirrors the Apartment Bookings, Gym and Spa
 * modules: a search box, quick status pills, a toggleable advanced panel
 * (sort + "added" date range), a live result count and a Clear-all shortcut.
 */
class Drivers extends Component
{
    public string $search = '';

    /** Quick pill: '' (all) | available | on_trip | off_duty */
    public string $statusFilter = '';

    /** Advanced: name | recent | trips */
    public string $sort = 'name';

    /** Advanced: "added" (created_at) date range. */
    public string $from = '';

    public string $to = '';

    /** Whether the advanced filter panel is open. */
    public bool $showFilters = false;

    /* ----- Add / Edit modal state ----- */
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fPhone = '';

    public string $fLicense = '';

    public string $fVehicle = '';

    public string $fStatus = Driver::AVAILABLE;

    public string $fNotes = '';

    protected function rules(): array
    {
        return [
            'fName' => ['required', 'string', 'max:120'],
            'fPhone' => ['nullable', 'string', 'max:40'],
            'fLicense' => ['nullable', 'string', 'max:60'],
            'fVehicle' => ['nullable', 'string', 'max:120'],
            'fStatus' => ['required', Rule::in([Driver::AVAILABLE, Driver::OFF_DUTY])],
            'fNotes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function updated($name): void
    {
        // Reset an invalid custom range so the list never silently empties.
        if ($name === 'from' && $this->to && $this->from > $this->to) {
            $this->to = '';
        }
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'statusFilter', 'sort', 'from', 'to']);
        $this->sort = 'name';
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $d = Driver::find($id);
        if (! $d) {
            return;
        }

        $this->editingId = $d->id;
        $this->fName = $d->name;
        $this->fPhone = (string) $d->phone;
        $this->fLicense = (string) $d->license_no;
        $this->fVehicle = (string) $d->vehicle_details;
        $this->fStatus = $d->status;
        $this->fNotes = (string) $d->notes;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'fName', 'fPhone', 'fLicense', 'fVehicle', 'fStatus', 'fNotes']);
        $this->fStatus = Driver::AVAILABLE;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'name' => $data['fName'],
            'phone' => $data['fPhone'] ?: null,
            'license_no' => $data['fLicense'] ?: null,
            'vehicle_details' => $data['fVehicle'] ?: null,
            'status' => $data['fStatus'],
            'notes' => $data['fNotes'] ?: null,
        ];

        if ($this->editingId) {
            $d = Driver::find($this->editingId);
            if (! $d) {
                return;
            }
            $d->update($payload);
            $message = $d->name.' was updated.';
        } else {
            $payload['sort_order'] = (int) Driver::max('sort_order') + 1;
            $d = Driver::create($payload);
            $message = $d->name.' was added to the roster.';
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /** Flip a driver between available and off-duty without opening the form. */
    public function toggleStatus(int $id): void
    {
        $d = Driver::find($id);
        if (! $d) {
            return;
        }

        $d->update(['status' => $d->isAvailable() ? Driver::OFF_DUTY : Driver::AVAILABLE]);
        $this->dispatch('toast', type: 'success', message: $d->name.' is now '.$d->statusLabel().'.');
    }

    public function delete(int $id): void
    {
        $d = Driver::find($id);
        if (! $d) {
            return;
        }

        // Soft delete keeps historical pickup records; the assignment simply reads
        // as removed on any booking that still references this driver.
        $name = $d->name;
        $d->delete();
        $this->dispatch('toast', type: 'success', message: $name.' was removed from the roster.');
    }

    /** The roster query with the current search + filters applied. */
    protected function baseQuery()
    {
        return Driver::query()
            ->withCount(['bookings as active_trips_count' => fn ($q) => $q->where('pickup_status', 'assigned')])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")
                ->orWhere('vehicle_details', 'like', "%{$this->search}%")
                ->orWhere('license_no', 'like', "%{$this->search}%")))
            ->when($this->statusFilter === 'available', fn ($q) => $q->where('status', Driver::AVAILABLE))
            ->when($this->statusFilter === 'off_duty', fn ($q) => $q->where('status', Driver::OFF_DUTY))
            ->when($this->statusFilter === 'on_trip', fn ($q) => $q
                ->whereHas('bookings', fn ($b) => $b->where('pickup_status', 'assigned')))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to));
    }

    public function render()
    {
        $total = Driver::count();
        $availableCount = Driver::where('status', Driver::AVAILABLE)->count();
        $offDutyCount = Driver::where('status', Driver::OFF_DUTY)->count();
        $onTrip = Driver::whereHas('bookings', fn ($q) => $q->where('pickup_status', 'assigned'))->count();

        $drivers = $this->baseQuery()
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'recent', fn ($q) => $q->latest())
            ->when($this->sort === 'trips', fn ($q) => $q->orderByDesc('active_trips_count')->orderBy('name'))
            ->get();

        $hasFilters = $this->search !== '' || $this->statusFilter !== ''
            || $this->from !== '' || $this->to !== '' || $this->sort !== 'name';

        return view('admin.vehicles.drivers', [
            'drivers' => $drivers,
            'filteredCount' => $drivers->count(),
            'hasFilters' => $hasFilters,
            'stats' => [
                ['label' => 'Total Drivers', 'value' => $total, 'sub' => 'On the roster', 'accent' => '#f38c00'],
                ['label' => 'Available', 'value' => $availableCount, 'sub' => 'Ready to assign', 'accent' => '#16a34a'],
                ['label' => 'On a Trip', 'value' => $onTrip, 'sub' => 'Currently assigned', 'accent' => '#7c3aed'],
                ['label' => 'Off Duty', 'value' => $offDutyCount, 'sub' => 'Not assignable', 'accent' => '#6b7280'],
            ],
            'statusTabs' => [
                '' => ['All Drivers', $total],
                'available' => ['Available', $availableCount],
                'on_trip' => ['On a Trip', $onTrip],
                'off_duty' => ['Off Duty', $offDutyCount],
            ],
        ])->layout('components.admin.app', [
            'title' => 'Drivers',
            'subtitle' => 'Manage the pickup driver roster',
        ]);
    }
}
