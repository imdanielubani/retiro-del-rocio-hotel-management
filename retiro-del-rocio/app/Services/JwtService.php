<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Issues and verifies HS256 JWTs for staff tablet sessions.
 * Secret + TTL come from config/jwt.php (driven by the backend .env).
 */
class JwtService
{
    /**
     * Issue a signed JWT for the given claims.
     *
     * @param  array<string, mixed>  $claims
     * @return array{token: string, expires_at: int, expires_in: int}
     */
    public function issue(array $claims, ?int $ttlMinutes = null): array
    {
        $ttl = ($ttlMinutes ?? (int) config('jwt.ttl')) * 60; // seconds
        $now = time();
        $exp = $now + $ttl;

        $payload = array_merge($claims, [
            'iss' => config('app.url'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
        ]);

        return [
            'token' => JWT::encode($payload, $this->secret(), config('jwt.algo')),
            'expires_at' => $exp,
            'expires_in' => $ttl,
        ];
    }

    /**
     * Decode + verify a JWT. Throws on an invalid/expired token.
     *
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key($this->secret(), config('jwt.algo')));
    }

    private function secret(): string
    {
        return (string) config('jwt.secret');
    }
}
