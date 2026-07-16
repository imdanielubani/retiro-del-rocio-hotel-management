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

// Mark devices Offline when their heartbeat goes stale (timeout in config/devices.php).
Schedule::command('devices:sweep-offline')->everyMinute()->withoutOverlapping();

// Confirm visitors who used their one-time TTLock code at the gate, and expire
// passes whose window has elapsed. No-op when TTLock isn't configured.
Schedule::command('visitors:reconcile-entries')->everyMinute()->withoutOverlapping();
