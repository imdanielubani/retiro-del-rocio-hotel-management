<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a staff request by its Bearer JWT (issued at staff-login).
 * Binds the resolved user so downstream controllers can use `$request->user()`.
 */
class AuthenticateStaffJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (\Throwable $e) {
            // Expired or tampered token.
            return response()->json(['message' => 'Session expired. Please sign in again.'], 401);
        }

        $user = User::find($claims['sub'] ?? null);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt', $claims);

        return $next($request);
    }
}
