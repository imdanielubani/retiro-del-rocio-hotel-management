<?php

namespace App\Console\Commands;

use App\Models\VisitorPass;
use App\Services\TTLockException;
use App\Services\TTLockService;
use App\Services\VisitorPassProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Confirms visitors who let themselves in with their one-time TTLock code, and
 * expires codes that were never used.
 *
 * A visitor's online passcode is not consumed by the officer — they punch it into
 * the gate lock themselves. TTLock has no webhook here, so we poll the lock's
 * unlock records: any record whose passcode matches a still-open pass means that
 * visitor is now inside. We mark the pass verified (via 'lock') and delete the
 * code so it is truly one-time. Runs every minute from the scheduler.
 */
class ReconcileVisitorEntries extends Command
{
    protected $signature = 'visitors:reconcile-entries {--lookback=15 : Minutes of lock history to scan}';

    protected $description = 'Confirm visitors who used their TTLock code, and expire stale passes.';

    public function handle(TTLockService $ttlock, VisitorPassProvisioner $provisioner): int
    {
        // Expire passes whose window has closed, whether or not TTLock is up.
        $this->expireStale($provisioner);

        $lockIds = $provisioner->gateLockIds();
        if (! $ttlock->isConfigured() || empty($lockIds)) {
            return self::SUCCESS; // Offline mode — nothing to poll.
        }

        // Every pass with a live online code, keyed by the code so a record from
        // any gate can be matched.
        $open = VisitorPass::open()
            ->where('ttlock_status', 'active')
            ->whereNotNull('online_code')
            ->get()
            ->keyBy('online_code');

        if ($open->isEmpty()) {
            return self::SUCCESS;
        }

        $lookback = max(1, (int) $this->option('lookback'));
        $start = Carbon::now()->subMinutes($lookback);
        $end = Carbon::now();

        $confirmed = 0;
        foreach ($lockIds as $lockId) {
            try {
                $records = $ttlock->lockRecords($lockId, $start, $end);
            } catch (TTLockException $e) {
                $this->warn('Could not read records for lock '.$lockId.': '.$e->getMessage());

                continue; // Try the next gate; retry this one next tick.
            }

            foreach ($records as $record) {
                if ((int) ($record['success'] ?? 0) !== 1) {
                    continue;
                }
                $used = (string) ($record['keyboardPwd'] ?? '');
                $pass = $used !== '' ? $open->get($used) : null;
                if (! $pass) {
                    continue;
                }

                // The visitor is inside. Confirm it (no officer — they let
                // themselves in) and delete the code from EVERY gate so it can't
                // be reused at the other lock.
                if ($pass->grant(null, 'lock')) {
                    $provisioner->revoke($pass);
                    $pass->forceFill(['ttlock_status' => 'used'])->save();
                    $open->forget($used); // Don't double-confirm within this run.
                    $confirmed++;
                }
            }
        }

        if ($confirmed > 0) {
            $this->info("Confirmed {$confirmed} visitor entr".($confirmed === 1 ? 'y' : 'ies').'.');
        }

        return self::SUCCESS;
    }

    /** Close out passes whose visit window has elapsed without being used. */
    private function expireStale(VisitorPassProvisioner $provisioner): void
    {
        $stale = VisitorPass::open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $pass) {
            $provisioner->revoke($pass);
            $pass->forceFill([
                'status' => VisitorPass::EXPIRED,
                'ttlock_status' => $pass->keyboard_pwd_id ? 'deleted' : $pass->ttlock_status,
            ])->save();
        }
    }
}
