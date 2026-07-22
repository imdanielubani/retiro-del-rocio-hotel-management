<?php

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\SosController;
use App\Http\Controllers\Api\V1\TabletController;
use App\Http\Controllers\Api\V1\VisitorPassController;
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

        // The tablet re-checking its own pairing at launch. Declared before the
        // `tablets/{code}` lookup below so "me" is not read as a device code.
        Route::get('tablets/me', [TabletController::class, 'me'])->name('api.v1.tablets.me');

        // Device self-reporting (called by the tablet with its device token).
        Route::post('tablets/heartbeat', [TabletController::class, 'heartbeat'])->name('api.v1.tablets.heartbeat');
        Route::post('tablets/sync', [TabletController::class, 'sync'])->name('api.v1.tablets.sync');

        // The tablet's live room occupancy + checked-in guest (guest welcome).
        Route::get('tablets/room-status', [TabletController::class, 'roomStatus'])->name('api.v1.tablets.room-status');

        // Emergency SOS from a guest's in-room tablet. Raising is throttled — a
        // panicking guest may hammer the button — but the endpoint is idempotent,
        // so a burst still yields exactly one alert for the room.
        Route::get('sos/active', [SosController::class, 'active'])->name('api.v1.sos.active');
        Route::post('sos', [SosController::class, 'store'])
            ->middleware('throttle:20,1')->name('api.v1.sos.store');
        Route::post('sos/{alert}/cancel', [SosController::class, 'cancel'])
            ->middleware('throttle:20,1')->name('api.v1.sos.cancel');

        // Visitor passes issued from a guest's in-room tablet — the guest invites
        // a visitor and the server mints a unique entry code. Scoped to the room.
        Route::get('visitor-passes', [VisitorPassController::class, 'index'])
            ->name('api.v1.visitor-passes.index');
        Route::post('visitor-passes', [VisitorPassController::class, 'store'])
            ->middleware('throttle:30,1')->name('api.v1.visitor-passes.store');

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

    // --- Security tablet (staff JWT, security role) ---
    // The security officer signs in on the security station, then their JWT
    // (not the device token) authorises the hotel-wide dashboard and lets them
    // acknowledge / resolve incoming SOS incidents. The role is re-checked in
    // the controller on every call.
    Route::middleware('jwt')->group(function () {
        Route::get('security/overview', [SecurityController::class, 'overview'])
            ->name('api.v1.security.overview');
        Route::get('security/incidents', [SecurityController::class, 'incidents'])
            ->name('api.v1.security.incidents');
        Route::post('security/incidents/{alert}/respond', [SecurityController::class, 'respond'])
            ->middleware('throttle:60,1')->name('api.v1.security.incidents.respond');
        Route::post('security/incidents/{alert}/resolve', [SecurityController::class, 'resolve'])
            ->middleware('throttle:60,1')->name('api.v1.security.incidents.resolve');

        // Visitor verification at the gate — list today's passes, look one up by
        // the code the visitor quotes, and admit them.
        Route::get('security/visitors', [SecurityController::class, 'visitors'])
            ->name('api.v1.security.visitors');
        Route::post('security/visitors/verify', [SecurityController::class, 'verifyCode'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.verify');
        Route::post('security/visitors/{pass}/grant', [SecurityController::class, 'grant'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.grant');
        Route::post('security/visitors/{pass}/deny', [SecurityController::class, 'deny'])
            ->middleware('throttle:60,1')->name('api.v1.security.visitors.deny');
    });
});
