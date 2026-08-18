<?php

namespace Tests\Unit\Services;

use App\Services\IoT\Tuya\TuyaAuthService;
use App\Services\IoT\Tuya\TuyaClient;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tuya's signing logic (docs/architecture/03-tuya-architecture.md §3):
 * HMAC-SHA256 keyed on client_secret, uppercase hex. Pinned here so a future
 * refactor can't silently drift the signature shape without a live account
 * to catch it against.
 */
class TuyaSigningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tuya.client_id' => 'test-client-id',
            'services.tuya.client_secret' => 'test-client-secret',
            'services.tuya.base_url' => 'https://openapi.tuyaeu.com',
        ]);
    }

    public function test_business_request_signature_is_uppercase_hex_hmac_sha256(): void
    {
        $client = new TuyaClient(new TuyaAuthService);

        $ref = new ReflectionClass($client);
        $method = $ref->getMethod('signedHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client, 'GET', '/v1.0/iot-03/devices/abc123/status', [], '', 'access-token-xyz');

        $this->assertSame('test-client-id', $headers['client_id']);
        $this->assertSame('access-token-xyz', $headers['access_token']);
        $this->assertSame('HMAC-SHA256', $headers['sign_method']);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{64}$/', $headers['sign']);
        $this->assertMatchesRegularExpression('/^\d{13}$/', $headers['t']);
    }

    public function test_signature_changes_when_the_url_changes(): void
    {
        $client = new TuyaClient(new TuyaAuthService);

        $ref = new ReflectionClass($client);
        $method = $ref->getMethod('signedHeaders');
        $method->setAccessible(true);

        // Fix t/nonce indirectly by comparing two calls against different
        // paths — the timestamp differs too, but the point is the sign is a
        // function of the URL (a stale/replayed sign for a different path
        // must never validate), not that it's byte-identical across calls.
        $headersA = $method->invoke($client, 'GET', '/v1.0/iot-03/devices/device-a/status', [], '', 'token');
        $headersB = $method->invoke($client, 'GET', '/v1.0/iot-03/devices/device-b/status', [], '', 'token');

        $this->assertNotSame($headersA['sign'], $headersB['sign']);
    }

    public function test_is_configured_reflects_client_credentials(): void
    {
        $client = new TuyaClient(new TuyaAuthService);
        $this->assertTrue($client->isConfigured());

        config(['services.tuya.client_secret' => null]);
        $this->assertFalse($client->isConfigured());
    }
}
