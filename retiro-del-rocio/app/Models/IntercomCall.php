<?php

namespace App\Models;

use App\Events\IntercomCallRinging;
use App\Events\IntercomCallUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A voice-call signal between a guest's room tablet and a staff station
 * (today, only Reception on the staff side). There is no real audio here —
 * this is the ringing/answer/decline/hang-up state machine the two tablets'
 * call screens render, mirroring how a real intercom would behave.
 *
 * Lifecycle: ringing → accepted → ended, or ringing → declined, or
 * ringing → cancelled (the caller hangs up before being answered).
 */
class IntercomCall extends Model
{
    public const RINGING = 'ringing';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const ENDED = 'ended';

    /** Still needs UI attention on one or both tablets. */
    public const ACTIVE_STATUSES = [self::RINGING, self::ACCEPTED];

    protected $fillable = [
        'from_room_unit_id', 'from_role', 'from_label', 'from_sublabel',
        'to_room_unit_id', 'to_role', 'to_label', 'to_sublabel',
        'status', 'answered_at', 'ended_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Every status change rings or updates whichever tablets are party to the
     * call, hung off the model so no code path can change a call and forget
     * to notify. Broadcasting is an accelerator, never a dependency — a
     * broadcaster outage never blocks placing or answering a call, the
     * tablets just fall back to their own poll.
     */
    protected static function booted(): void
    {
        static::created(function (self $call) {
            try {
                broadcast(new IntercomCallRinging($call->to_room_unit_id, $call->to_role));
            } catch (Throwable $e) {
                report($e);
            }
        });

        static::updated(function (self $call) {
            if (! $call->wasChanged('status')) {
                return;
            }

            try {
                broadcast(new IntercomCallUpdated(
                    $call->from_room_unit_id,
                    $call->from_role,
                    $call->to_room_unit_id,
                    $call->to_role,
                ));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Active, plus one that ended moments ago — used only by "what's my
     * current call" lookups, so the side that *didn't* hang up still sees a
     * "Call Ended" screen for a beat before it disappears, instead of the
     * call vanishing the instant the other party ends it. Deliberately NOT
     * used for the "already on a call" conflict check: that must free up
     * the moment a call ends, not five seconds later.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', self::ACTIVE_STATUSES)
                ->orWhere(function (Builder $q2) {
                    $q2->whereNotIn('status', self::ACTIVE_STATUSES)
                        ->where('ended_at', '>=', now()->subSeconds(5));
                });
        });
    }

    /** Whether [$roomUnitId]/[$role] (exactly one set) is the caller on this call. */
    public function isCaller(?int $roomUnitId, ?string $role): bool
    {
        return $this->matchesParty($this->from_room_unit_id, $this->from_role, $roomUnitId, $role);
    }

    /** Whether [$roomUnitId]/[$role] (exactly one set) is the callee on this call. */
    public function isCallee(?int $roomUnitId, ?string $role): bool
    {
        return $this->matchesParty($this->to_room_unit_id, $this->to_role, $roomUnitId, $role);
    }

    private function matchesParty(?int $partyRoomUnitId, ?string $partyRole, ?int $roomUnitId, ?string $role): bool
    {
        if ($roomUnitId !== null) {
            return $partyRoomUnitId === $roomUnitId;
        }

        return $role !== null && $partyRole === $role;
    }

    public function accept(): bool
    {
        if ($this->status !== self::RINGING) {
            return false;
        }

        return $this->update(['status' => self::ACCEPTED, 'answered_at' => now()]);
    }

    public function decline(): bool
    {
        if ($this->status !== self::RINGING) {
            return false;
        }

        return $this->update(['status' => self::DECLINED, 'ended_at' => now()]);
    }

    /**
     * The single "End Call" action both call screens use — its meaning
     * depends on where the call currently is: hanging up before the other
     * side answers cancels it, hanging up mid-call ends it.
     */
    public function hangUp(): bool
    {
        if ($this->status === self::RINGING) {
            return $this->update(['status' => self::CANCELLED, 'ended_at' => now()]);
        }

        if ($this->status === self::ACCEPTED) {
            return $this->update(['status' => self::ENDED, 'ended_at' => now()]);
        }

        return false;
    }

    /** The payload either tablet's call screen renders. */
    public function toCallArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'from' => [
                'room_unit_id' => $this->from_room_unit_id,
                'role' => $this->from_role,
                'label' => $this->from_label,
                'sublabel' => $this->from_sublabel,
            ],
            'to' => [
                'room_unit_id' => $this->to_room_unit_id,
                'role' => $this->to_role,
                'label' => $this->to_label,
                'sublabel' => $this->to_sublabel,
            ],
            'started_at' => optional($this->created_at)->toIso8601String(),
            'answered_at' => optional($this->answered_at)->toIso8601String(),
            'ended_at' => optional($this->ended_at)->toIso8601String(),
        ];
    }
}
