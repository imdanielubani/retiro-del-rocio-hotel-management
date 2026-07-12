<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Inject the cookie-consent banner before </body> on public pages.
        $middleware->web(append: [
            \Statikbe\CookieConsent\CookieConsentMiddleware::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // Staff tablet JWT auth (tablet app staff sessions).
            'jwt' => \App\Http\Middleware\AuthenticateStaffJwt::class,
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
