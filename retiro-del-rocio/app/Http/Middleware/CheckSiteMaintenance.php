<?php

namespace App\Http\Middleware;

use App\Livewire\Admin\Settings\Index;
use App\Support\HotelSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-site maintenance gate, toggled from Settings → Website
 * ({@see Index}).
 *
 * Every admin *page* route is named `admin.*`, so it is always exempt here —
 * the person who turned maintenance mode on must always be able to reach the
 * dashboard to turn it back off, regardless of `ADMIN_DOMAIN` vs `/admin`
 * prefix mode.
 *
 * That name check alone is NOT enough, though: the admin dashboard is built
 * on Livewire, and every wire:click/wire:model interaction — including the
 * login form itself — is an AJAX POST to Livewire's own shared "update"
 * endpoint, which carries no `admin.` route name at all. Blocking it would
 * silently break every button on the dashboard while the page shell still
 * loaded fine, which is worse than an obvious outage. Livewire's JS/CSS
 * asset routes and the `X-Livewire` header it sends on every request are
 * exempted for the same reason, and `broadcasting/auth` (Reverb
 * private-channel auth) for the admin's own notification channel.
 *
 * API routes never carry this middleware at all (registered on the `web`
 * group only, never `api`), so staff/guest tablets keep working unaffected —
 * this class never even runs for them.
 */
class CheckSiteMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) ($request->route()?->getName());

        $exempt = str_starts_with($routeName, 'admin.')
            || str_starts_with($request->path(), 'livewire')
            || $request->hasHeader('X-Livewire')
            || $request->is('broadcasting/auth');

        if ($exempt || ! HotelSettings::maintenanceMode()) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => HotelSettings::maintenanceMessage(),
        ], 503);
    }
}
