<?php

namespace App\Services\IoT\Tuya;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Low-level signed HTTP client for the Tuya Cloud OpenAPI. Builds the
 * `stringToSign`, signs with HMAC-SHA256, sends, and unwraps
 * `{success, result, code, msg}` — Tuya's API returns HTTP 200 even on
 * logical errors, same convention TTLockService applies to its "errcode".
 *
 * Token acquisition/refresh/caching lives in {@see TuyaAuthService}; this
 * class only knows how to sign and send one request given a (possibly empty)
 * access token.
 */
class TuyaClient
{
    public function __construct(protected TuyaAuthService $auth) {}

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return config("services.tuya.$key", $default);
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->cfg('base_url', 'https://openapi.tuyaeu.com'), '/');
    }

    public function clientId(): ?string
    {
        return $this->cfg('client_id');
    }

    public function clientSecret(): ?string
    {
        return $this->cfg('client_secret');
    }

    /** True when the minimum credentials are present in config/.env. */
    public function isConfigured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    protected function http(): PendingRequest
    {
        return Http::timeout((int) $this->cfg('timeout', 15))
            ->retry((int) $this->cfg('retries', 2), 500, throw: false);
    }

    /**
     * GET a Tuya business endpoint, signed with the current access token.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed> the unwrapped `result`
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * POST a Tuya business endpoint, signed with the current access token.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> the unwrapped `result`
     */
    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, body: $body);
    }

    /**
     * Sign and send a business API request, retrying once (with a token
     * refresh) if Tuya reports the token invalid/expired.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $query = [], array $body = [], bool $retryOnAuth = true): array
    {
        if (! $this->isConfigured()) {
            throw new TuyaException('Tuya is not configured. Set TUYA_* values in your .env file.');
        }

        $accessToken = $this->auth->getAccessToken();
        $url = $this->baseUrl().$path.($query ? '?'.http_build_query($query) : '');
        $bodyJson = $body ? json_encode($body) : '';

        $headers = $this->signedHeaders($method, $path, $query, $bodyJson, $accessToken);

        $response = $method === 'GET'
            ? $this->http()->withHeaders($headers)->get($this->baseUrl().$path, $query)
            : $this->http()->withHeaders($headers)->withBody($bodyJson, 'application/json')->post($this->baseUrl().$path);

        if ($response->failed()) {
            throw new TuyaException("Tuya request to {$path} failed (HTTP {$response->status()}).");
        }

        $payload = $response->json() ?? [];

        // Exact Tuya token-invalid error code is unconfirmed against the live
        // account (see docs/architecture/03-tuya-architecture.md §7) — stubbed
        // behind a config value so it can be filled in without a code change
        // once verified.
        $tokenErrorCodes = (array) $this->cfg('token_error_codes', []);

        if ($retryOnAuth && in_array((string) ($payload['code'] ?? ''), $tokenErrorCodes, true)) {
            $this->auth->refreshAccessToken();

            return $this->send($method, $path, $query, $body, retryOnAuth: false);
        }

        if (($payload['success'] ?? false) !== true) {
            throw new TuyaException(
                'Tuya API error: '.($payload['msg'] ?? 'unknown').' ['.$path.']',
                isset($payload['code']) ? (string) $payload['code'] : null,
            );
        }

        return $payload['result'] ?? [];
    }

    /**
     * Business API request signature per
     * docs/architecture/03-tuya-architecture.md §3: sign string is
     * `client_id + access_token + t + nonce + stringToSign`, where
     * `stringToSign = HTTPMethod\nContentSHA256\nHeaders\nURL` — HMAC-SHA256
     * keyed on `client_secret`, uppercase hex.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    protected function signedHeaders(string $method, string $path, array $query, string $bodyJson, string $accessToken): array
    {
        $t = (string) now()->getTimestampMs();
        $nonce = (string) Str::uuid();
        $contentSha256 = hash('sha256', $bodyJson);
        $url = $path.($query ? '?'.http_build_query($query) : '');

        $stringToSign = implode("\n", [$method, $contentSha256, '', $url]);
        $signSource = $this->clientId().$accessToken.$t.$nonce.$stringToSign;
        $sign = strtoupper(hash_hmac('sha256', $signSource, (string) $this->clientSecret()));

        return [
            'client_id' => $this->clientId(),
            'access_token' => $accessToken,
            'sign' => $sign,
            't' => $t,
            'nonce' => $nonce,
            'sign_method' => 'HMAC-SHA256',
        ];
    }
}
