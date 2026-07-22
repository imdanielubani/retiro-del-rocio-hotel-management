<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT (staff session tokens for the tablet app)
    |--------------------------------------------------------------------------
    | Staff sign-ins on the tablets are issued short-lived JWTs. The `exp` claim
    | drives the app's session-expiring warning + timeout. Falls back to APP_KEY
    | as the signing secret when JWT_SECRET is not set.
    */

    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    'algo' => env('JWT_ALGO', 'HS256'),

    // Token lifetime in minutes (default 8 hours — one shift).
    'ttl' => (int) env('JWT_TTL', 480),

];
