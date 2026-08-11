<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A visitor pass a checked-in guest issues from the in-room tablet.
 *
 * Lifecycle: pending → verified (security let them in) | denied (turned away),
 * or pending → cancelled (guest revoked) | expired.
 */
class VisitorPass extends Model
{
    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const DENIED = 'denied';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** Still awaiting the gate — security must see these, and the code is live. */
    public const OPEN_STATUSES = [self::PENDING];

    protected $fillable = [
        'device_id', 'room_unit_id', 'booking_id',
        'host_name', 'room_number', 'suite_name',
        'visitor_name', 'visitor_email', 'visitor_phone',
        'code', 'online_code', 'status',
        'keyboard_pwd_id', 'lock_id', 'ttlock_grants', 'ttlock_status', 'ttlock_error',
        'verified_at', 'denied_at', 'cancelled_at', 'handled_by', 'verified_via',
        'expires_at', 'exited_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'denied_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'exited_at' => 'datetime',
        'ttlock_grants' => 'array',
    ];

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

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * The visitor is admitted, exactly once. Guarded so a spent pass cannot be
     * re-verified and two stations (or a lock read + a keypad tap) both settle
     * cleanly. $via records how: 'keypad' (officer keyed the offline code) or
     * 'lock' (the visitor used the online TTLock code themselves — no officer).
     */
    public function grant(?User $officer, string $via = 'keypad'): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return $this->update([
            'status' => self::VERIFIED,
            'verified_at' => now(),
            'verified_via' => $via,
            'handled_by' => $officer?->id,
        ]);
    }

    /**
     * The visitor is turned away at the gate. Guarded like grant() so a settled
     * pass can't be flipped; the officer (or admin) is recorded on handled_by.
     */
    public function deny(?User $officer): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return $this->update([
            'status' => self::DENIED,
            'denied_at' => now(),
            'handled_by' => $officer?->id,
        ]);
    }

    /** True while the online (TTLock) code is live on the gate lock. */
    public function hasLiveOnlineCode(): bool
    {
        return $this->isOpen()
            && filled($this->online_code)
            && filled($this->keyboard_pwd_id)
            && $this->ttlock_status === 'active';
    }

    /**
     * Every gate lock the online code lives on, with the keyboardPwdId to remove
     * it. Falls back to the legacy single-lock columns for passes issued before
     * multi-gate support.
     *
     * @return array<int, array{lockId:string, keyboardPwdId:string}>
     */
    public function revokableGrants(): array
    {
        $grants = collect($this->ttlock_grants ?: [])
            ->filter(fn ($g) => ! empty($g['lockId']) && ! empty($g['keyboardPwdId']))
            ->map(fn ($g) => [
                'lockId' => (string) $g['lockId'],
                'keyboardPwdId' => (string) $g['keyboardPwdId'],
            ])
            ->values()
            ->all();

        if (empty($grants) && $this->keyboard_pwd_id && $this->lock_id) {
            $grants = [[
                'lockId' => (string) $this->lock_id,
                'keyboardPwdId' => (string) $this->keyboard_pwd_id,
            ]];
        }

        return $grants;
    }

    /** A verified visitor who has not been marked as having left. */
    public function isInside(): bool
    {
        return $this->status === self::VERIFIED && $this->exited_at === null;
    }

    /** Record that a verified visitor has left. Guarded and idempotent. */
    public function markExited(): bool
    {
        if ($this->status !== self::VERIFIED || $this->exited_at !== null) {
            return false;
        }

        return $this->update(['exited_at' => now()]);
    }

    /* ---------------- Admin display ---------------- */

    /** The register's collapsed status: pending | inside | exited | denied | … */
    public function adminStatus(): string
    {
        return match (true) {
            $this->status === self::VERIFIED && $this->exited_at === null => 'inside',
            $this->status === self::VERIFIED => 'exited',
            default => $this->status,
        };
    }

    public function adminStatusLabel(): string
    {
        return match ($this->adminStatus()) {
            'pending' => 'Pending',
            'inside' => 'Inside',
            'exited' => 'Exited',
            'denied' => 'Denied',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            default => ucfirst($this->status),
        };
    }

    /** [text, background] hex for the status pill. */
    public function adminStatusColors(): array
    {
        return match ($this->adminStatus()) {
            'inside' => ['#16a34a', '#dcfce7'],
            'pending' => ['#d97706', '#fef3c7'],
            'exited' => ['#475569', '#eef2f6'],
            'denied' => ['#dc2626', '#fee2e2'],
            'cancelled', 'expired' => ['#6b7280', '#f3f4f6'],
            default => ['#6b7280', '#f3f4f6'],
        };
    }

    /** How the visitor got in, for the register's "Entry" column. */
    public function entryMethodLabel(): string
    {
        return match ($this->verified_via) {
            'lock' => 'Lock',
            'keypad' => 'Keypad',
            default => '—',
        };
    }

    /** The access outcome for the log: Lock | Keypad | Denied | —. */
    public function accessOutcomeLabel(): string
    {
        return match (true) {
            $this->verified_via === 'lock' => 'Lock',
            $this->verified_via === 'keypad' => 'Keypad',
            $this->status === self::DENIED => 'Denied',
            default => '—',
        };
    }

    /** Seconds the visitor has been (or was) inside, or null if never entered. */
    public function timeInsideSeconds(): ?int
    {
        if (! $this->verified_at) {
            return null;
        }

        return (int) $this->verified_at->diffInSeconds($this->exited_at ?? now());
    }

    /** Compact "45m", "1h 20m" time-inside, or an em dash. */
    public function timeInsideLabel(): string
    {
        $seconds = $this->timeInsideSeconds();

        return $seconds === null ? '—' : self::humanDurationInside($seconds);
    }

    /** Compact human duration, e.g. 45s, 45m, 1h 20m. */
    public static function humanDurationInside(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes.'m';
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    /** The TTLock (online code) state, human-readable. */
    public function ttlockLabel(): string
    {
        return match ($this->ttlock_status) {
            'pending' => 'Issuing…',
            'active' => 'Online',
            'offline' => 'Offline',
            'failed' => 'Failed',
            'used' => 'Used',
            'deleted' => 'Removed',
            default => '—',
        };
    }

    /**
     * Mint a 6-digit code no still-open pass is using in EITHER channel (offline
     * `code` or `online_code`), so every live code at the gate is unambiguous when
     * an officer keys it. Pass $exclude to also avoid a sibling code being minted
     * in the same breath. Falls back after enough tries rather than looping
     * forever (the space is 900k, collisions are rare).
     *
     * @param  string[]  $exclude
     */
    public static function generateUniqueCode(array $exclude = []): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = (string) random_int(100000, 999999);
            if (in_array($code, $exclude, true)) {
                continue;
            }
            $taken = self::open()
                ->where(fn ($q) => $q->where('code', $code)->orWhere('online_code', $code))
                ->exists();
            if (! $taken) {
                return $code;
            }
        }

        return (string) random_int(100000, 999999);
    }

    /**
     * The human-facing pass reference, e.g. "VP-2607-001" — VP, the two-digit
     * year and month it was issued, then the zero-padded id.
     */
    public function caseNumber(): string
    {
        $when = $this->created_at ?? now();

        return 'VP-'.$when->format('ym').'-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /** Up-to-two-letter initials for the visitor's avatar disc. */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->visitor_name)) ?: [];
        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)));

        return $letters->isNotEmpty() ? $letters->implode('') : 'V';
    }

    /**
     * The payload the security tablet renders — for the Visitor Verification list
     * and the matched-pass card. Carries the host + contact so the officer can
     * confirm the visitor against the room they claim to be visiting.
     */
    public function toSecurityArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->caseNumber(),
            'status' => $this->status,
            'visitor_name' => $this->visitor_name,
            'host_name' => $this->host_name,
            'room_number' => $this->room_number,
            'suite_name' => $this->suite_name,
            // The manual (offline) code lives in `code`; the online code is the
            // TTLock passcode. Both are surfaced so the officer sees what the
            // visitor is holding; the keypad accepts either.
            'code' => $this->code,
            'offline_code' => $this->code,
            'online_code' => $this->hasLiveOnlineCode() ? $this->online_code : null,
            'ttlock_status' => $this->ttlock_status,
            'verified_via' => $this->verified_via,
            'email' => $this->visitor_email,
            'phone' => $this->visitor_phone,
            'submitted_label' => optional($this->created_at)->format('h:iA'),
            'created_at' => optional($this->created_at)->toIso8601String(),
            // Lets the tablet offer "Check Out" only for a visitor who is
            // actually inside — verified, and not already marked as left.
            'is_inside' => $this->isInside(),
            'arrival_label' => optional($this->verified_at)->format('h:iA'),
        ];
    }

    /**
     * A visitor row for the dashboard's "Visitors Today" list.
     *
     * Covers the expected as well as the arrived: a pass still pending is a
     * visitor who is simply "Not Inside" yet, which is the state Figma 257:1295
     * draws. So neither the verified chip nor the presence pill may be assumed.
     */
    public function toVisitorRowArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->visitor_name,
            // The row identifies the visit by its case number (Figma 257:1275),
            // not by a code that is already spent by the time they are inside.
            'reference' => $this->caseNumber(),
            'suite_name' => $this->suite_name,
            'room_number' => $this->room_number,
            'pass_code' => $this->online_code ?: $this->code,
            // When they walked in, or when they were invited if they have not.
            'arrival_label' => optional($this->verified_at ?? $this->created_at)->format('h:iA'),
            'is_inside' => $this->isInside(),
            'is_verified' => $this->status === self::VERIFIED,
            // Distinguishes "checked out" from "never arrived" — both read as
            // `is_inside: false`, but the tablet must not show a departed
            // visitor as merely "Not Inside".
            'is_exited' => $this->status === self::VERIFIED && $this->exited_at !== null,
        ];
    }

    /**
     * A pass for the dashboard's "Visitor Pass Requests" column.
     *
     * The column is a feed of the gate's recent work, not a pending-only queue
     * (Figma 257:1336 shows verified entries sitting alongside pending ones), so
     * `is_verified` reflects the real status rather than being assumed.
     */
    public function toPassRequestArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->caseNumber(),
            'name' => $this->visitor_name,
            'suite_name' => $this->suite_name,
            'room_number' => $this->room_number,
            'pass_code' => $this->code,
            'online_code' => $this->hasLiveOnlineCode() ? $this->online_code : null,
            'offline_code' => $this->code,
            'email' => $this->visitor_email,
            'whatsapp' => $this->visitor_phone,
            'submitted_label' => optional($this->created_at)->format('h:iA'),
            'arrival_label' => optional($this->verified_at)->format('h:iA'),
            'is_verified' => $this->status === self::VERIFIED,
        ];
    }

    /**
     * A visitor row for the guest tablet's My Stay "Guests" card — the people
     * this guest has invited, with a friendly, guest-facing status.
     */
    public function toStayVisitorArray(): array
    {
        $status = $this->adminStatus();

        return [
            'id' => $this->id,
            'name' => $this->visitor_name,
            'initials' => $this->initials(),
            'status' => $status,
            'status_label' => match ($status) {
                'pending' => 'Invited',
                'inside' => 'Inside',
                'exited' => 'Checked out',
                'denied' => 'Denied',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
                default => ucfirst($status),
            },
        ];
    }

    /**
     * A visitor row for the reception tablet's Visitor Pass screen — every
     * visitor invited, arrived or otherwise, read-only (reception doesn't
     * grant or deny gate access; that stays with security).
     */
    public function toReceptionVisitorArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->caseNumber(),
            'visitor_name' => $this->visitor_name,
            'initials' => $this->initials(),
            'host_name' => $this->host_name,
            'room_number' => $this->room_number,
            'suite_name' => $this->suite_name,
            'email' => $this->visitor_email,
            'phone' => $this->visitor_phone,
            'status' => $this->adminStatus(),
            'status_label' => $this->adminStatusLabel(),
            'invited_label' => optional($this->created_at)->format('M j, h:iA'),
            'arrival_label' => optional($this->verified_at)->format('h:iA'),
            'is_inside' => $this->isInside(),
        ];
    }

    /** The payload the guest tablet renders for a history row. */
    public function toTabletArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->caseNumber(),
            'status' => $this->status,
            'visitor_name' => $this->visitor_name,
            'visitor_email' => $this->visitor_email,
            'visitor_phone' => $this->visitor_phone,
            // The visitor's primary code is the TTLock (online) one when live,
            // with the manual offline code always available as a fallback.
            'code' => $this->online_code ?: $this->code,
            'online_code' => $this->hasLiveOnlineCode() ? $this->online_code : null,
            'offline_code' => $this->code,
            'ttlock_status' => $this->ttlock_status,
            'verified_via' => $this->verified_via,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
