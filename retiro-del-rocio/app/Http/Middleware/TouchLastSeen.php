<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps `last_seen_at` on whichever principal (a guest/staff {@see Device}
 * token, or a staff {@see User} JWT) authenticated the request — the sole
 * source of the "online" dot on every chat surface. Any authenticated call
 * counts as presence, not just an explicit heartbeat, so a tablet that's
 * simply being used (polling its own dashboard, sending a chat message)
 * already shows as online.
 *
 * Throttled to one write per 20 seconds per principal — every route in the
 * app is behind this, so an unconditional `save()` on every request would
 * turn presence into the single busiest write path in the database.
 */
class TouchLastSeen
{
    private const THROTTLE_SECONDS = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();

        if ($principal instanceof User || $principal instanceof Device) {
            $stale = ! $principal->last_seen_at
                || $principal->last_seen_at->lt(now()->subSeconds(self::THROTTLE_SECONDS));

            if ($stale) {
                // Presence is not a "last modified" — leave updated_at alone so
                // this never disturbs an admin screen sorted by it.
                $principal->timestamps = false;
                $principal->forceFill(['last_seen_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
