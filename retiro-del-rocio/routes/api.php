<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — consumed by the Flutter tablet/mobile apps.
|--------------------------------------------------------------------------
| Registered under the `/api` prefix and the `api` middleware group in
| bootstrap/app.php. Token auth via Laravel Sanctum (auth:sanctum) — a
| SEPARATE guard from the web session used by the website + admin dashboard,
| so nothing here affects those. Versioned under /v1 from day one.
*/

// On its own sub-domain (API_DOMAIN) the API lives at /v1; locally, with no
// domain configured, it falls back to /api/v1. The `apiPrefix` is set to '' in
// bootstrap/app.php so this file owns the full path.
$apiDomain = config('app.api_domain');

$api = Route::prefix($apiDomain ? 'v1' : 'api/v1');

if ($apiDomain) {
    $api->domain($apiDomain);
}

$api->group(function () {
    // --- Public ---
    Route::get('health', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->name('api.v1.health');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.login');

    // --- Authenticated (Sanctum bearer token) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    });
});
