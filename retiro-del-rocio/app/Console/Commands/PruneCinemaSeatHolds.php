<?php

namespace App\Console\Commands;

use App\Models\CinemaSeatHold;
use Illuminate\Console\Command;

/**
 * Deletes stale seat holds — temporary reservations (cinema_booking_id IS NULL)
 * whose expiry has passed. Booked seats (expires_at NULL) are never touched, so
 * confirmed bookings keep their lock. Availability already ignores expired holds;
 * this just keeps the table tidy.
 *
 *   php artisan cinema:prune-seat-holds
 */
class PruneCinemaSeatHolds extends Command
{
    protected $signature = 'cinema:prune-seat-holds';

    protected $description = 'Delete expired (unbooked) cinema seat holds';

    public function handle(): int
    {
        $deleted = CinemaSeatHold::query()
            ->whereNull('cinema_booking_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$deleted} expired seat hold(s).");

        return self::SUCCESS;
    }
}
