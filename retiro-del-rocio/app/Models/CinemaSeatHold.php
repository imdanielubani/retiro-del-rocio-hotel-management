<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CinemaSeatHold extends Model
{
    protected $fillable = [
        'movie_id', 'show_date', 'show_time', 'seat', 'token', 'cinema_booking_id', 'expires_at',
    ];

    protected $casts = [
        'show_date' => 'date',
        'expires_at' => 'datetime',
    ];

    /** How long a pre-payment hold stays reserved while the guest checks out. */
    public const HOLD_MINUTES = 20;

    public function scopeForShowing($q, $movieId, $date, $time)
    {
        return $q->where('movie_id', $movieId)
            ->whereDate('show_date', $date)
            ->where('show_time', $time);
    }

    /** Booked seats (permanent) plus holds that haven't expired. */
    public function scopeActive($q)
    {
        return $q->where(function ($w) {
            $w->whereNotNull('cinema_booking_id')
                ->orWhere('expires_at', '>', now());
        });
    }

    /** Seats that are unavailable for a showing right now. */
    public static function takenSeats($movieId, $date, $time): array
    {
        return static::query()->forShowing($movieId, $date, $time)->active()
            ->pluck('seat')->unique()->values()->all();
    }

    /**
     * Reserve seats for a token before payment. Returns ['ok' => bool, 'conflicts' => [...]].
     * The unique index guarantees a seat can never be held/booked twice.
     */
    public static function placeHold($movieId, $date, $time, array $seats, string $token, int $minutes = self::HOLD_MINUTES): array
    {
        return DB::transaction(function () use ($movieId, $date, $time, $seats, $token, $minutes) {
            // Drop this token's previous (unbooked) holds for the showing — handles re-selection.
            static::query()->forShowing($movieId, $date, $time)
                ->where('token', $token)->whereNull('cinema_booking_id')->delete();

            $conflicts = [];

            foreach (array_unique($seats) as $seat) {
                $row = static::query()->forShowing($movieId, $date, $time)
                    ->where('seat', $seat)->lockForUpdate()->first();

                if ($row) {
                    // Booked, or actively held by someone else → unavailable.
                    if ($row->cinema_booking_id || ($row->expires_at && $row->expires_at->isFuture())) {
                        $conflicts[] = $seat;

                        continue;
                    }
                    // Expired & unbooked → reclaim it.
                    $row->update(['token' => $token, 'expires_at' => now()->addMinutes($minutes)]);
                } else {
                    static::create([
                        'movie_id' => $movieId, 'show_date' => $date, 'show_time' => $time,
                        'seat' => $seat, 'token' => $token, 'expires_at' => now()->addMinutes($minutes),
                    ]);
                }
            }

            if ($conflicts) {
                // Roll back the holds we just placed so we don't strand seats.
                static::query()->forShowing($movieId, $date, $time)
                    ->where('token', $token)->whereNull('cinema_booking_id')->delete();

                return ['ok' => false, 'conflicts' => array_values($conflicts)];
            }

            return ['ok' => true];
        });
    }

    /**
     * Convert a token's holds into permanent seats tied to a booking, claiming any
     * uncovered seats atomically. Returns the seats actually secured.
     */
    public static function claimForBooking($movieId, $date, $time, array $seats, string $token, CinemaBooking $booking): array
    {
        $secured = [];

        DB::transaction(function () use ($movieId, $date, $time, $seats, $token, $booking, &$secured) {
            foreach (array_unique($seats) as $seat) {
                $row = static::query()->forShowing($movieId, $date, $time)
                    ->where('seat', $seat)->lockForUpdate()->first();

                if (! $row) {
                    static::create([
                        'movie_id' => $movieId, 'show_date' => $date, 'show_time' => $time,
                        'seat' => $seat, 'token' => $token, 'cinema_booking_id' => $booking->id, 'expires_at' => null,
                    ]);
                    $secured[] = $seat;
                } elseif ($row->cinema_booking_id === $booking->id) {
                    $secured[] = $seat;
                } elseif (is_null($row->cinema_booking_id)
                    && ($row->token === $token || is_null($row->expires_at) || $row->expires_at->isPast())) {
                    // Our own hold, or an expired/unbooked one → claim permanently.
                    $row->update(['token' => $token, 'cinema_booking_id' => $booking->id, 'expires_at' => null]);
                    $secured[] = $seat;
                }
                // else: booked or actively held by another token → lost (conflict).
            }
        });

        return $secured;
    }

    /** Free a token's unbooked holds (guest abandoned checkout). */
    public static function release(string $token): void
    {
        static::query()->where('token', $token)->whereNull('cinema_booking_id')->delete();
    }
}
