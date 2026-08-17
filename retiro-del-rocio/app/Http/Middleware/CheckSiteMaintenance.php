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
 * Every admin route is named `admin.*`, so it is always exempt here — the
 * person who turned maintenance mode on must always be able to reach the
 * dashboard to turn it back off, regardless of `ADMIN_DOMAIN` vs `/admin`
 * prefix mode. API routes never carry this middleware at all (registered on
 * the `web` group only), so staff/guest tablets keep working unaffected.
 */
class CheckSiteMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) ($request->route()?->getName());

        if (str_starts_with($routeName, 'admin.') || ! HotelSettings::maintenanceMode()) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => HotelSettings::maintenanceMessage(),
        ], 503);
    }
}
