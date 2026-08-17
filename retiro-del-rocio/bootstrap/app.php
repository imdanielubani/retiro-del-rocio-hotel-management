<?php

use App\Http\Middleware\AuthenticateStaffJwt;
use App\Http\Middleware\CheckSiteMaintenance;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Statikbe\CookieConsent\CookieConsentMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // Empty: routes/api.php sets its own prefix (/v1 on the API sub-domain,
        // /api/v1 locally) so it can be domain-scoped in staging/production.
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Running behind a reverse proxy (Dokploy/Traefik) that terminates TLS.
        // Trust it so Laravel detects the original HTTPS request — keeps asset URLs,
        // redirects and secure cookies on https instead of plain http.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        // Inject the cookie-consent banner before </body> on public pages, and
        // gate the public site behind the Settings → Website maintenance
        // toggle (admin routes are always exempt — see the middleware itself).
        $middleware->web(append: [
            CookieConsentMiddleware::class,
            CheckSiteMaintenance::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // Staff tablet JWT auth (tablet app staff sessions).
            'jwt' => AuthenticateStaffJwt::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients always get JSON errors (401/422/etc.), never an HTML
        // redirect to the admin login. Web/admin behaviour is unchanged.
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
