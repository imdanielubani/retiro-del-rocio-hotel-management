<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the TTLock Cloud (OpenAPI).
 *
 * All credentials come from config/.env (config('services.ttlock.*')).
 * The initial OAuth tokens are seeded from .env; refreshed tokens are held
 * in the cache at runtime (no database table for credentials).
 *
 * All TTLock timestamps are epoch milliseconds. The API returns HTTP 200
 * even on logical errors, signalled by a non-zero "errcode" in the body.
 */
class TTLockService
{
    // TTLock error codes that mean the access token is stale/invalid.
    protected const TOKEN_ERROR_CODES = [10003, 80000];

    // Cache key holding the current (possibly refreshed) token bundle.
    protected const TOKEN_CACHE_KEY = 'ttlock.token';

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.ttlock.$key", $default);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->cfg('base_url', 'https://euapi.ttlock.com'), '/');
    }

    protected function clientId(): ?string
    {
        return $this->cfg('client_id');
    }

    protected function clientSecret(): ?string
    {
        return $this->cfg('client_secret');
    }

    /** True when the minimum credentials are present in config/.env. */
    public function isConfigured(): bool
    {
        return filled($this->clientId())
            && filled($this->clientSecret())
            && (filled($this->currentToken()) || filled($this->refreshToken()));
    }

    protected function http(): PendingRequest
    {
        return Http::asForm()
            ->timeout((int) $this->cfg('timeout', 15))
            ->retry((int) $this->cfg('retries', 2), 500, throw: false);
    }

    /* ===================== OAuth tokens ===================== */

    /** The active access token: cached (refreshed) value, else the .env value. */
    protected function currentToken(): ?string
    {
        return Cache::get(self::TOKEN_CACHE_KEY.'.access') ?: $this->cfg('access_token');
    }

    protected function refreshToken(): ?string
    {
        return Cache::get(self::TOKEN_CACHE_KEY.'.refresh') ?: $this->cfg('refresh_token');
    }

    protected function tokenIsExpired(): bool
    {
        $expiresAt = Cache::get(self::TOKEN_CACHE_KEY.'.expires_at');

        // Unknown expiry (only the static .env token so far) → treat as usable;
        // a rejected call will trigger a refresh via the errcode path.
        return $expiresAt !== null && Carbon::parse($expiresAt)->isPast();
    }

    /** Return a valid access token, refreshing if we know it has expired. */
    public function getAccessToken(): string
    {
        if ($this->tokenIsExpired() && $this->refreshToken()) {
            return $this->refreshAccessToken();
        }

        $token = $this->currentToken();
        if (! $token) {
            throw new TTLockException('TTLock access token is not configured.');
        }

        return $token;
    }

    /** Exchange the refresh token for a new access token; cache the result. */
    public function refreshAccessToken(): string
    {
        if (! $this->refreshToken()) {
            throw new TTLockException('No TTLock refresh token available.');
        }

        $response = $this->http()->post($this->baseUrl().'/oauth2/token', [
            'clientId' => $this->clientId(),
            'clientSecret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken(),
        ]);

        $body = $response->json() ?? [];

        if (empty($body['access_token'])) {
            throw new TTLockException('TTLock token refresh failed: '.($body['errmsg'] ?? 'unknown error'));
        }

        $ttl = isset($body['expires_in']) ? max(60, (int) $body['expires_in'] - 60) : 60 * 60 * 24 * 29;
        Cache::put(self::TOKEN_CACHE_KEY.'.access', $body['access_token'], $ttl);
        Cache::put(self::TOKEN_CACHE_KEY.'.refresh', $body['refresh_token'] ?? $this->refreshToken(), 60 * 60 * 24 * 30);
        Cache::put(self::TOKEN_CACHE_KEY.'.expires_at', Carbon::now()->addSeconds($ttl)->toIso8601String(), $ttl);

        return $body['access_token'];
    }

    /* ===================== API request helper ===================== */

    /**
     * POST to a TTLock endpoint with auth params attached.
     * Refreshes the token once and retries if the API reports a stale token.
     */
    protected function call(string $endpoint, array $params, bool $retryOnAuth = true): array
    {
        $payload = array_merge($params, [
            'clientId' => $this->clientId(),
            'accessToken' => $this->getAccessToken(),
            'date' => (string) Carbon::now()->getTimestampMs(),
        ]);

        $response = $this->http()->post($this->baseUrl().$endpoint, $payload);

        if ($response->failed()) {
            throw new TTLockException("TTLock request to {$endpoint} failed (HTTP {$response->status()}).");
        }

        $body = $response->json() ?? [];
        $errcode = $body['errcode'] ?? 0;

        if ($retryOnAuth && in_array($errcode, self::TOKEN_ERROR_CODES, true) && $this->refreshToken()) {
            // Token rejected — refresh and retry once.
            $this->refreshAccessToken();

            return $this->call($endpoint, $params, retryOnAuth: false);
        }

        if ($errcode) {
            throw new TTLockException("TTLock API error {$errcode}: ".($body['errmsg'] ?? 'unknown').' ['.$endpoint.']');
        }

        return $body;
    }

    /* ===================== Passcodes ===================== */

    /**
     * Generate a time-bound passcode for a lock.
     *
     * Uses /v3/keyboardPwd/add — we supply a self-generated numeric code that
     * is pushed to the lock via the gateway (addType). The alternative
     * /v3/keyboardPwd/get (cloud-generated code) requires a special account
     * permission that most apps don't have and returns errcode -2018.
     *
     * Pass an explicit $passcode to reuse the same digits across several gate
     * locks (multi-gate setups) — the guest then has one code for all gates.
     *
     * @return array{keyboardPwd:string, keyboardPwdId:int}
     */
    public function createPasscode(string $lockId, Carbon $start, Carbon $end, ?string $name = null, ?string $passcode = null): array
    {
        // Custom TTLock passcodes are 4–9 digits; use a 6-digit code (no leading zero).
        $passcode = $passcode ?: (string) random_int(100000, 999999);

        $body = $this->call('/v3/keyboardPwd/add', [
            'lockId' => $lockId,
            'keyboardPwd' => $passcode,
            'keyboardPwdName' => Str::limit($name ?: 'Guest', 30, ''),
            'startDate' => (string) $start->getTimestampMs(),
            'endDate' => (string) $end->getTimestampMs(),
            // 1=bluetooth (needs SDK), 2=gateway, 3=NB-IoT. Gateway can call the API directly.
            'addType' => (int) $this->cfg('add_type', 2),
        ]);

        if (empty($body['keyboardPwdId'])) {
            throw new TTLockException('TTLock did not return a passcode id.');
        }

        return [
            'keyboardPwd' => $passcode,
            'keyboardPwdId' => (int) $body['keyboardPwdId'],
        ];
    }

    /** Permanently delete a passcode, immediately revoking access. */
    public function deletePasscode(string $lockId, int|string $keyboardPwdId): void
    {
        $this->call('/v3/keyboardPwd/delete', [
            'lockId' => $lockId,
            'keyboardPwdId' => $keyboardPwdId,
            'deleteType' => 2, // delete via gateway/wifi
        ]);
    }

    /** Change the validity window of an existing passcode. */
    public function updatePasscode(string $lockId, int|string $keyboardPwdId, Carbon $start, Carbon $end): void
    {
        $this->call('/v3/keyboardPwd/changePeriod', [
            'lockId' => $lockId,
            'keyboardPwdId' => $keyboardPwdId,
            'startDate' => (string) $start->getTimestampMs(),
            'endDate' => (string) $end->getTimestampMs(),
            'changeType' => 2, // change via gateway/wifi
        ]);
    }

    /* ===================== QR codes ===================== */

    // NOTE: QR generation was intentionally removed — guests receive a passcode
    // only. The /v3/qrCode/add H5 link forced SMS identity verification on any
    // new device, making it unsuitable to send to guests. deleteQrCode() is kept
    // so access issued before that change can still be cleaned up on revoke.

    /** Delete an official QR code (best-effort; used on cancellation). */
    public function deleteQrCode(string $lockId, int|string $qrCodeId): void
    {
        $this->call('/v3/qrCode/delete', [
            'lockId' => $lockId,
            'qrCodeId' => $qrCodeId,
        ]);
    }

    /* ===================== Locks / diagnostics ===================== */

    /** Paginated list of locks on the account. */
    public function listLocks(int $pageNo = 1, int $pageSize = 100): array
    {
        $body = $this->call('/v3/lock/list', [
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ]);

        return $body['list'] ?? [];
    }

    /** Detail for a single lock (used by the per-lock "test connection" check). */
    public function lockDetail(string $lockId): array
    {
        return $this->call('/v3/lock/detail', ['lockId' => $lockId]);
    }

    /**
     * Verify credentials + connectivity.
     *
     * @return array{ok:bool, message:string, locks?:int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'TTLock is not configured. Set TTLOCK_* values in your .env file.'];
        }

        try {
            $locks = $this->listLocks(1, 100);

            return [
                'ok' => true,
                'message' => 'Connected to TTLock. '.count($locks).' lock(s) found on the account.',
                'locks' => count($locks),
            ];
        } catch (TTLockException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
