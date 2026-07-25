<?php

namespace App\Models;

use App\Events\SosAlertChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * An emergency raised from an in-room tablet.
 *
 * Lifecycle: active → acknowledged → resolved, or active → cancelled.
 */
class SosAlert extends Model
{
    public const ACTIVE = 'active';

    public const ACKNOWLEDGED = 'acknowledged';

    public const RESOLVED = 'resolved';

    public const CANCELLED = 'cancelled';

    /** Still needs a response — security must see these. */
    public const OPEN_STATUSES = [self::ACTIVE, self::ACKNOWLEDGED];

    protected $fillable = [
        'device_id', 'room_unit_id', 'booking_id',
        'room_number', 'suite_name', 'guest_name',
        'status', 'raised_at', 'acknowledged_at', 'resolved_at', 'cancelled_at',
        'acknowledged_by', 'resolved_by', 'cancelled_by', 'notes',
    ];

    protected $casts = [
        'raised_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Any change to an alert is pushed to the security tablets and the dashboard
     * immediately — a person is waiting. Hung off the model so no code path can
     * change an alert and forget to notify.
     *
     * Broadcasting must never break raising an alert: if the socket server is
     * down the alert is still recorded, and the tablets pick it up on their poll.
     */
    protected static function booted(): void
    {
        static::created(fn (self $alert) => $alert->announce());

        static::updated(function (self $alert) {
            if ($alert->wasChanged('status')) {
                $alert->announce();
            }
        });
    }

    private function announce(): void
    {
        try {
            broadcast(new SosAlertChanged($this->id, $this->status, $this->room_unit_id));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Security sees the alert: acknowledge it (they are on their way) and, once
     * dealt with, resolve it. Both are guarded so a stale tablet cannot reopen a
     * closed incident or double-acknowledge.
     */
    public function acknowledge(User $officer): bool
    {
        if ($this->status !== self::ACTIVE) {
            return false;
        }

        return $this->update([
            'status' => self::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $officer->id,
        ]);
    }

    public function resolve(User $officer): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return $this->update([
            'status' => self::RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $officer->id,
        ]);
    }

    /** The payload the guest tablet renders. */
    public function toTabletArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'room_number' => $this->room_number,
            'suite_name' => $this->suite_name,
            'guest_name' => $this->guest_name,
            'raised_at' => optional($this->raised_at)->toIso8601String(),
            'acknowledged_at' => optional($this->acknowledged_at)->toIso8601String(),
        ];
    }

    /**
     * The human-facing case reference, e.g. "SOS-2607-140" — SOS, the two-digit
     * year and month it was raised, then the zero-padded id.
     */
    public function caseNumber(): string
    {
        $when = $this->raised_at ?? $this->created_at ?? now();

        return 'SOS-'.$when->format('ym').'-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /** Seconds from the alert being raised to security acknowledging it. */
    public function responseSeconds(): ?int
    {
        if (! $this->raised_at || ! $this->acknowledged_at) {
            return null;
        }

        return (int) $this->raised_at->diffInSeconds($this->acknowledged_at);
    }

    /** Seconds from the alert being raised to it being resolved. */
    public function resolutionSeconds(): ?int
    {
        if (! $this->raised_at || ! $this->resolved_at) {
            return null;
        }

        return (int) $this->raised_at->diffInSeconds($this->resolved_at);
    }

    public function responseLabel(): string
    {
        return self::humanDuration($this->responseSeconds());
    }

    public function resolutionLabel(): string
    {
        return self::humanDuration($this->resolutionSeconds());
    }

    /** Compact human duration, e.g. 45s, 3m 20s, 1h 05m — or an em dash. */
    public static function humanDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            $rem = $seconds % 60;

            return $rem ? "{$minutes}m {$rem}s" : "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $mins = str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);

        return "{$hours}h {$mins}m";
    }

    /** The richer payload the security dashboard renders for an incident. */
    public function toSecurityArray(): array
    {
        return [
            'id' => $this->id,
            'case_no' => $this->caseNumber(),
            'status' => $this->status,
            'room_number' => $this->room_number,
            'suite_name' => $this->suite_name,
            'guest_name' => $this->guest_name ?? 'Guest',
            'raised_at' => optional($this->raised_at)->toIso8601String(),
            'acknowledged_at' => optional($this->acknowledged_at)->toIso8601String(),
            'acknowledged_by' => optional($this->acknowledgedBy)->name,
        ];
    }

    /**
     * A compact alert row for the reception dashboard's "Alerts" panel: a title,
     * a relative time and a severity so the row can be colour-coded.
     */
    public function toReceptionAlertArray(): array
    {
        $room = $this->room_number ? 'Room '.$this->room_number : ($this->suite_name ?? 'the hotel');

        return [
            'id' => $this->id,
            'title' => 'Emergency SOS — '.$room,
            'time_label' => optional($this->raised_at)->diffForHumans(['short' => true]) ?? '',
            'severity' => $this->status === self::ACTIVE ? 'high' : 'medium',
        ];
    }

    /**
     * The full incident record for the SOS Alert Logs and its detail panel —
     * every status, with the whole timeline (who acted, and when).
     */
    public function toLogArray(): array
    {
        return [
            'id' => $this->id,
            'case_no' => $this->caseNumber(),
            'status' => $this->status,
            'room_number' => $this->room_number,
            'suite_name' => $this->suite_name,
            'guest_name' => $this->guest_name ?? 'Guest',
            'raised_at' => optional($this->raised_at)->toIso8601String(),
            'acknowledged_at' => optional($this->acknowledged_at)->toIso8601String(),
            'acknowledged_by' => optional($this->acknowledgedBy)->name,
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'resolved_by' => optional($this->resolvedBy)->name,
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
        ];
    }
}
