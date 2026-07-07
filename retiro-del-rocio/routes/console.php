<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clear out expired (unbooked) cinema seat holds every 10 minutes so the
// holds table doesn't accumulate stale rows. Booked seats are never touched.
Schedule::command('cinema:prune-seat-holds')->everyTenMinutes()->withoutOverlapping();
