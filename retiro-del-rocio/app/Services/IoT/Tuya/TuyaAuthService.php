<?php

namespace App\Services\IoT\Tuya;

use App\Services\TTLockService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tuya OAuth token acquisition/refresh/cache, mirroring
 * {@see TTLockService}'s cached-token shape but adapted to
 * Tuya's actual signing (docs/architecture/03-tuya-architecture.md §3):
 *
 * Token request (`GET /v1.0/token?grant_type=1`): sign string is
 * `client_id + t + nonce`, HMAC-SHA256 keyed on `client_secret`, uppercase
 * hex, sent as header `sign` alongside `t` (13-digit ms timestamp),
 * `sign_method: HMAC-SHA256`, `client_id`. No `access_token` header on this
 * call. Response: `access_token`, `refresh_token`, `expire_time` (seconds).
 */
class TuyaAuthService
{
    protected const TOKEN_CACHE_KEY = 'tuya.token';

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.tuya.$key", $default);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->cfg('base_url', 'https://openapi.tuyaeu.com'), '/');
    }

    protected function clientId(): ?string
    {
        return $this->cfg('client_id');
    }

    protected function clientSecret(): ?string
    {
        return $this->cfg('client_secret');
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg('timeout', 15))
            ->retry((int) $this->cfg('retries', 2), 500, throw: false);
    }

    protected function cachedAccessToken(): ?string
    {
        return Cache::get(self::TOKEN_CACHE_KEY.'.access');
    }

    protected function cachedRefreshToken(): ?string
    {
        return Cache::get(self::TOKEN_CACHE_KEY.'.refresh');
    }

    protected function tokenIsExpired(): bool
    {
        $expiresAt = Cache::get(self::TOKEN_CACHE_KEY.'.expires_at');

        return $expiresAt === null || Carbon::parse($expiresAt)->isPast();
    }

    /** Return a valid access token, fetching/refreshing as needed. */
    public function getAccessToken(): string
    {
        if (! $this->tokenIsExpired() && $this->cachedAccessToken()) {
            return $this->cachedAccessToken();
        }

        if ($this->cachedRefreshToken()) {
            return $this->refreshAccessToken();
        }

        return $this->requestAccessToken();
    }

    /** Exchange client credentials for a fresh token pair (grant_type=1). */
    public function requestAccessToken(): string
    {
        if (! $this->isConfigured()) {
            throw new TuyaException('Tuya is not configured. Set TUYA_* values in your .env file.');
        }

        $response = $this->http()
            ->withHeaders($this->signedHeaders('GET', '/v1.0/token', ['grant_type' => 1]))
            ->get($this->baseUrl().'/v1.0/token', ['grant_type' => 1]);

        return $this->storeTokenResponse($response->json() ?? []);
    }

    /** Exchange the cached refresh token for a new access token. */
    public function refreshAccessToken(): string
    {
        $refreshToken = $this->cachedRefreshToken();

        if (! $refreshToken) {
            return $this->requestAccessToken();
        }

        $path = '/v1.0/token/'.$refreshToken;

        $response = $this->http()
            ->withHeaders($this->signedHeaders('GET', $path))
            ->get($this->baseUrl().$path);

        return $this->storeTokenResponse($response->json() ?? []);
    }

    /**
     * Sign a token-endpoint request. Tuya requires the full `stringToSign`
     * composition (`HTTPMethod\nContentSHA256\nHeaders\nURL`) on *every*
     * request including token acquisition — the older `client_id + t + nonce`
     * only form is rejected ("sign invalid") on current accounts. Mirrors
     * {@see TuyaClient::signedHeaders()} but without an `access_token`
     * (unavailable/irrelevant for this endpoint).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    protected function signedHeaders(string $method, string $path, array $query = []): array
    {
        $t = (string) now()->getTimestampMs();
        $nonce = (string) Str::uuid();
        $contentSha256 = hash('sha256', '');
        $url = $path.($query ? '?'.http_build_query($query) : '');

        $stringToSign = implode("\n", [$method, $contentSha256, '', $url]);
        $signSource = $this->clientId().$t.$nonce.$stringToSign;
        $sign = strtoupper(hash_hmac('sha256', $signSource, (string) $this->clientSecret()));

        return [
            'client_id' => $this->clientId(),
            'sign' => $sign,
            't' => $t,
            'nonce' => $nonce,
            'sign_method' => 'HMAC-SHA256',
        ];
    }

    /** @param  array<string, mixed>  $payload */
    protected function storeTokenResponse(array $payload): string
    {
        $result = $payload['result'] ?? [];

        if (($payload['success'] ?? false) !== true || empty($result['access_token'])) {
            throw new TuyaException('Tuya token request failed: '.($payload['msg'] ?? 'unknown error'));
        }

        $ttl = isset($result['expire_time']) ? max(60, (int) $result['expire_time'] - 60) : 3600;

        Cache::put(self::TOKEN_CACHE_KEY.'.access', $result['access_token'], $ttl);
        Cache::put(self::TOKEN_CACHE_KEY.'.refresh', $result['refresh_token'] ?? $this->cachedRefreshToken(), 60 * 60 * 24 * 90);
        Cache::put(self::TOKEN_CACHE_KEY.'.expires_at', Carbon::now()->addSeconds($ttl)->toIso8601String(), $ttl);

        return $result['access_token'];
    }
}
