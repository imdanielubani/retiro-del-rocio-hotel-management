<?php

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\TabletController;
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

    // App bootstrap config (onboarding video URL, etc.) — consumed before a
    // device is provisioned, so it stays public.
    Route::get('app/config', [AppConfigController::class, 'show'])
        ->name('api.v1.app.config');

    // Staff session check — authenticated by the staff JWT (not the device token).
    Route::get('staff/me', function (\Illuminate\Http\Request $request) {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
        ]]);
    })->middleware('jwt')->name('api.v1.staff.me');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.v1.auth.login');

    // OTP password reset (tablet app): request code → verify → set new password.
    Route::post('auth/forgot-password', [PasswordResetController::class, 'sendOtp'])
        ->middleware('throttle:5,1')->name('api.v1.auth.forgot-password');
    Route::post('auth/verify-otp', [PasswordResetController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')->name('api.v1.auth.verify-otp');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')->name('api.v1.auth.reset-password');

    // Tablet provisioning — public, authenticated by the QR's provision token.
    Route::post('tablets/provision', [TabletController::class, 'provision'])
        ->middleware('throttle:12,1')
        ->name('api.v1.tablets.provision');

    // --- Authenticated (Sanctum bearer token) ---
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Device self-reporting (called by the tablet with its device token).
        Route::post('tablets/heartbeat', [TabletController::class, 'heartbeat'])->name('api.v1.tablets.heartbeat');
        Route::post('tablets/sync', [TabletController::class, 'sync'])->name('api.v1.tablets.sync');

        // The tablet's live room occupancy + checked-in guest (guest welcome).
        Route::get('tablets/room-status', [TabletController::class, 'roomStatus'])->name('api.v1.tablets.room-status');

        // Staff sign-in on a staff tablet (device token identifies the station).
        Route::post('tablets/staff-login', [TabletController::class, 'staffLogin'])
            ->middleware('throttle:10,1')->name('api.v1.tablets.staff-login');

        // Staff device management (user token + DevicePolicy).
        Route::get('devices', [DeviceController::class, 'index'])->name('api.v1.devices.index');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->whereNumber('device')->name('api.v1.devices.show');
        Route::get('tablets/{code}', [DeviceController::class, 'showByCode'])->name('api.v1.tablets.show');
        Route::post('tablets/restart', [TabletController::class, 'restart'])->name('api.v1.tablets.restart');
        Route::post('tablets/lock', [TabletController::class, 'lock'])->name('api.v1.tablets.lock');
        Route::post('tablets/unlock', [TabletController::class, 'unlock'])->name('api.v1.tablets.unlock');
    });
});
