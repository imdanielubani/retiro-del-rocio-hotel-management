<?php

namespace App\Livewire\Admin\Spa;

use App\Mail\SpaCancellation;
use App\Mail\SpaReservation;
use App\Models\SpaBooking;
use App\Models\SpaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Bookings extends Component
{
    use WithPagination;

    public string $search = '';

    // Quick date filter (matches the Payments module): '' | today | 7d | 30d | month
    public string $range = '';

    public bool $showFilters = false;

    // Advanced filters.
    public string $year = '';

    public string $month = '';

    public string $day = '';

    public string $from = '';

    public string $to = '';

    public string $status = '';        // confirmed | pending | cancelled

    public string $payment = '';       // paid | pending | refunded

    // Detail slide-over.
    public bool $showDetail = false;

    public ?int $selectedId = null;

    // Add Booking modal.
    public bool $showCreate = false;

    public string $cName = '';

    public string $cEmail = '';

    public string $cPhone = '';

    public string $cServiceSlug = '';

    public int $cGuests = 1;

    public string $cDate = '';

    public string $cTime = '';

    public bool $cMarkPaid = true;

    protected $validationAttributes = [
        'cName' => 'guest name',
        'cEmail' => 'email',
        'cServiceSlug' => 'service',
        'cGuests' => 'guests',
        'cDate' => 'date',
        'cTime' => 'time',
    ];

    public function updating($name): void
    {
        // Any filter change resets pagination (skip modal/detail fields).
        if (in_array($name, ['search', 'range', 'year', 'month', 'day', 'from', 'to', 'status', 'payment'], true)) {
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        $this->range = $this->range === $range ? '' : $range;
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function clearAll(): void
    {
        $this->reset(['search', 'range', 'year', 'month', 'day', 'from', 'to', 'status', 'payment']);
        $this->resetPage();
    }

    /* ---------------- Row actions ---------------- */

    public function viewDetails(int $id): void
    {
        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedId = null;
    }

    public function confirmSession(int $id): void
    {
        $b = SpaBooking::find($id);
        if (! $b) {
            return;
        }
        $b->update(['status' => 'confirmed']);
        $this->safeMail($b->customer_email, fn () => new SpaReservation($b));
        $this->dispatch('toast', type: 'success', message: 'Session '.$b->sessionCode().' confirmed — guest notified.');
    }

    public function markCompleted(int $id): void
    {
        $b = SpaBooking::find($id);
        if (! $b) {
            return;
        }
        $b->update(['status' => 'completed']);
        $this->dispatch('toast', type: 'success', message: 'Session '.$b->sessionCode().' marked completed.');
    }

    public function recordPayment(int $id): void
    {
        $b = SpaBooking::find($id);
        if (! $b) {
            return;
        }
        $b->update([
            'payment_status' => 'paid',
            'status' => $b->status === 'cancelled' ? 'cancelled' : 'confirmed',
            'paid_at' => $b->paid_at ?? now(),
        ]);
        if ($b->status !== 'cancelled') {
            $this->safeMail($b->customer_email, fn () => new SpaReservation($b));
        }
        $this->dispatch('toast', type: 'success', message: 'Payment recorded for '.$b->sessionCode().'.');
    }

    public function cancelSession(int $id): void
    {
        $b = SpaBooking::find($id);
        if (! $b) {
            return;
        }
        $b->update([
            'status' => 'cancelled',
            // A paid booking that gets cancelled becomes a refund.
            'payment_status' => $b->payment_status === 'paid' ? 'refunded' : $b->payment_status,
        ]);
        $this->safeMail($b->customer_email, fn () => new SpaCancellation($b));
        $this->dispatch('toast', type: 'success', message: 'Session '.$b->sessionCode().' cancelled — guest notified.');
    }

    /* ---------------- Add Booking ---------------- */

    public function openCreate(): void
    {
        $this->reset(['cName', 'cEmail', 'cPhone', 'cServiceSlug', 'cGuests', 'cDate', 'cTime', 'cMarkPaid']);
        $this->cGuests = 1;
        $this->cMarkPaid = true;
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function createBooking(): void
    {
        $data = $this->validate([
            'cName' => ['required', 'string', 'max:120'],
            'cEmail' => ['required', 'email', 'max:190'],
            'cPhone' => ['nullable', 'string', 'max:40'],
            'cServiceSlug' => ['required', 'string', 'exists:spa_services,slug'],
            'cGuests' => ['required', 'integer', 'min:1', 'max:30'],
            'cDate' => ['required', 'date', 'after_or_equal:today'],
            'cTime' => ['nullable', 'string', 'max:20'],
        ]);

        $service = SpaService::with('category')->where('slug', $data['cServiceSlug'])->first();
        $guests = (int) $data['cGuests'];
        $subtotal = $service->price * $guests;
        $fees = 2000;
        $taxes = (int) round($subtotal * 0.075);
        $total = $subtotal + $fees + $taxes;

        // 12-hour time label, e.g. "15:00" -> "3:00 PM".
        $timeLabel = null;
        if (! empty($data['cTime'])) {
            try {
                $timeLabel = Carbon::createFromFormat('H:i', substr($data['cTime'], 0, 5))->format('g:i A');
            } catch (\Throwable $e) {
                $timeLabel = $data['cTime'];
            }
        }

        $booking = SpaBooking::create([
            'reference' => 'SPA-ADM-'.strtoupper(Str::random(8)),
            'services' => [[
                'name' => $service->name,
                'slug' => $service->slug,
                'price' => $service->price,
                'guests' => $guests,
                'subtotal' => $subtotal,
                'duration_minutes' => $service->duration_minutes,
                'category' => $service->category?->name,
                'category_color' => $service->category?->color ?? '#6b7280',
            ]],
            'guests' => $guests,
            'date' => $data['cDate'],
            'time' => $timeLabel,
            'subtotal' => $subtotal,
            'fees' => $fees,
            'taxes' => $taxes,
            'total' => $total,
            'customer_name' => $data['cName'],
            'customer_email' => $data['cEmail'],
            'customer_phone' => $data['cPhone'] ?: null,
            'status' => 'confirmed',
            'payment_status' => $this->cMarkPaid ? 'paid' : 'pending',
            'payment_method' => 'manual',
            'paid_at' => $this->cMarkPaid ? now() : null,
        ]);

        $this->safeMail($booking->customer_email, fn () => new SpaReservation($booking));

        $this->showCreate = false;
        $this->dispatch('toast', type: 'success', message: 'Booking '.$booking->sessionCode().' created — guest notified.');
    }

    /** Send a mailable without letting a mail failure break the action. */
    protected function safeMail(?string $email, \Closure $make): void
    {
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send($make());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ---------------- Query ---------------- */

    protected function baseQuery()
    {
        return SpaBooking::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('customer_name', 'like', "%{$this->search}%")
                ->orWhere('customer_email', 'like', "%{$this->search}%")
                ->orWhere('id', 'like', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment))
            ->when($this->year, fn ($q) => $q->whereYear('date', $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth('date', $this->month))
            ->when($this->day, fn ($q) => $q->whereDay('date', $this->day))
            ->when($this->from, fn ($q) => $q->whereDate('date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('date', '<=', $this->to))
            ->when($this->range, function ($q) {
                [$start, $end] = $this->rangeBounds();
                if ($start) {
                    $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
                }
            });
    }

    protected function rangeBounds(): array
    {
        $now = Carbon::now();

        return match ($this->range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [null, null],
        };
    }

    public function render()
    {
        $total = SpaBooking::count();
        $confirmed = SpaBooking::where('status', 'confirmed')->count();
        $pending = SpaBooking::where('status', 'pending')->count();
        $revenue = (int) SpaBooking::where('payment_status', 'paid')->sum('total');

        $stats = [
            ['label' => 'Total Sessions', 'value' => $total, 'sub' => 'All spa bookings', 'accent' => '#f38c00'],
            ['label' => 'Confirmed', 'value' => $confirmed, 'sub' => 'Scheduled', 'accent' => '#16a34a'],
            ['label' => 'Pending', 'value' => $pending, 'sub' => 'Need confirmation', 'accent' => '#d97706'],
            ['label' => 'Revenue Collected', 'value' => '₦'.number_format($revenue), 'sub' => 'From paid sessions', 'accent' => '#7c3aed'],
        ];

        $hasFilters = (bool) ($this->search || $this->range || $this->year || $this->month
            || $this->day || $this->from || $this->to || $this->status || $this->payment);

        $filteredCount = (clone $this->baseQuery())->count();

        $bookings = $this->baseQuery()->latest('id')->paginate(8);

        // Metadata (category + duration) keyed by service slug — avoids per-row
        // lookups when a booking's stored JSON predates these fields.
        $serviceMeta = SpaService::with('category')->get()->keyBy('slug')->map(fn ($s) => [
            'duration' => $s->duration_minutes,
            'category' => $s->category?->name,
            'color' => $s->category?->color ?? '#6b7280',
        ])->toArray();

        $years = SpaBooking::query()
            ->selectRaw('DISTINCT YEAR(COALESCE(date, created_at)) as y')
            ->orderByDesc('y')->pluck('y')->filter()->values();

        $services = SpaService::active()->ordered()->get(['name', 'slug', 'price']);

        $selected = $this->showDetail && $this->selectedId
            ? SpaBooking::find($this->selectedId)
            : null;

        return view('admin.spa.bookings', [
            'bookings' => $bookings,
            'stats' => $stats,
            'hasFilters' => $hasFilters,
            'filteredCount' => $filteredCount,
            'serviceMeta' => $serviceMeta,
            'years' => $years,
            'services' => $services,
            'selected' => $selected,
        ])->layout('components.admin.app', [
            'title' => 'Spa Bookings',
            'subtitle' => 'Reservations made on the spa & wellness page',
        ]);
    }
}
