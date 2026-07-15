<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\DeviceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pairing is QR-only. The QR carries a device code *and* a provisioning token;
 * the token is the secret. A device code alone is readable off the dashboard, so
 * it must never be enough to bind a tablet to a suite.
 */
class TabletProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet']);

        $this->device = Device::create([
            'device_uuid' => (string) Str::uuid(),
            'device_code' => 'TAB-101',
            'device_name' => 'Room 101 Tablet',
            'device_type_id' => $type->id,
            'mode' => 'guest',
            'status' => 'offline',
            'is_provisioned' => false,
            'provision_token' => 'secret-token-123',
        ]);
    }

    public function test_a_qr_payload_pairs_the_tablet(): void
    {
        $this->postJson('/api/v1/tablets/provision', [
            'device_code' => 'TAB-101',
            'provision_token' => 'secret-token-123',
            'app_version' => '1.0.0',
        ])
            ->assertOk()
            ->assertJsonPath('device.device_code', 'TAB-101')
            ->assertJsonStructure(['token', 'token_type', 'device']);

        $this->assertTrue($this->device->fresh()->is_provisioned);
    }

    public function test_a_device_code_alone_is_rejected(): void
    {
        // The old "Enter Setup Code" flow. It is gone from the app, and the API
        // must not honour it either — otherwise anyone who can read a code off
        // the dashboard could bind their own tablet to a guest's suite.
        $this->postJson('/api/v1/tablets/provision', [
            'device_code' => 'TAB-101',
            'app_version' => '1.0.0',
        ])->assertJsonValidationErrors('provision_token');

        $this->assertFalse($this->device->fresh()->is_provisioned);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->postJson('/api/v1/tablets/provision', [
            'device_code' => 'TAB-101',
            'provision_token' => 'not-the-real-token',
        ])->assertJsonValidationErrors('device_code');

        $this->assertFalse($this->device->fresh()->is_provisioned);
    }
}
