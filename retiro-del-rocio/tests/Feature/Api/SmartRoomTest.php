<?php

namespace Tests\Feature\Api;

use App\Contracts\SmartDeviceProviderInterface;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\SmartDevice;
use App\Models\SmartScene;
use App\Models\SmartSceneAction;
use App\Services\IoT\Tuya\TuyaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guest Smart Room API: room-scoped device/scene access derived from the
 * Sanctum device token (never client input), capability/value validation
 * before any provider call, and the scene-activation command sequence. The
 * Tuya provider is faked throughout — no real Tuya calls are ever made.
 */
class SmartRoomTest extends TestCase
{
    use RefreshDatabase;

    private function roomWithDevice(): array
    {
        $room = Room::create([
            'name' => 'Alba Suite', 'slug' => 'alba-suite-'.Str::random(6),
            'type' => 'suite', 'price' => 7500, 'guests' => 4,
        ]);
        $unit = RoomUnit::create(['room_id' => $room->id, 'number' => (string) random_int(100, 999), 'status' => 'occupied']);

        $type = DeviceType::create(['name' => 'Tablet', 'slug' => 'tablet-'.Str::random(6)]);
        $device = Device::create([
            'device_uuid' => (string) Str::uuid(), 'device_code' => 'TAB-'.Str::random(6),
            'device_name' => 'Room Tablet', 'device_type_id' => $type->id, 'mode' => 'guest',
            'room_id' => $room->id, 'room_unit_id' => $unit->id, 'status' => 'online', 'is_provisioned' => true,
        ]);
        $token = $device->createToken('tablet')->plainTextToken;

        return [$room, $unit, $device, $token];
    }

    private function light(RoomUnit $unit, array $overrides = []): SmartDevice
    {
        return SmartDevice::create(array_merge([
            'room_unit_id' => $unit->id,
            'name' => 'Bedside Left',
            'type' => 'light',
            'provider' => 'tuya',
            'provider_device_id' => 'tuya-'.Str::random(10),
            'capabilities' => [
                'power' => ['code' => 'switch_led', 'type' => 'bool'],
                'brightness' => ['code' => 'bright_value_v2', 'type' => 'int', 'min' => 10, 'max' => 1000],
            ],
            'last_state' => ['switch_led' => false],
            'status' => 'online',
            'is_active' => true,
        ], $overrides));
    }

    private function fakeProvider(): FakeSmartDeviceProvider
    {
        $fake = new FakeSmartDeviceProvider;
        // Bound as a closure (not ->instance()) because the controller
        // resolves via app(Interface::class, ['provider' => ...]) — passing
        // parameters forces the container through the contextual-build path,
        // which ignores a plain ->instance() singleton but still calls a
        // bound closure.
        $this->app->bind(SmartDeviceProviderInterface::class, fn () => $fake);

        return $fake;
    }

    public function test_guest_lists_only_devices_in_its_own_room(): void
    {
        [, $unit, , $token] = $this->roomWithDevice();
        $this->light($unit, ['name' => 'Main Light']);

        [$otherRoom, $otherUnit] = $this->roomWithDevice();
        $this->light($otherUnit, ['name' => 'Other Room Light']);

        $response = $this->withToken($token)->getJson('/api/v1/guest/room/devices');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Main Light'));
        $this->assertFalse($names->contains('Other Room Light'));
    }

    public function test_guest_cannot_view_a_device_in_another_room(): void
    {
        [, , , $token] = $this->roomWithDevice();
        [, $otherUnit] = $this->roomWithDevice();
        $foreignDevice = $this->light($otherUnit);

        $this->withToken($token)
            ->getJson("/api/v1/guest/room/devices/{$foreignDevice->id}")
            ->assertForbidden();
    }

    public function test_guest_cannot_command_a_device_in_another_room(): void
    {
        $this->fakeProvider();
        [, , , $token] = $this->roomWithDevice();
        [, $otherUnit] = $this->roomWithDevice();
        $foreignDevice = $this->light($otherUnit);

        $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$foreignDevice->id}/command", [
                'capability' => 'power', 'value' => true,
            ])
            ->assertForbidden();
    }

    public function test_command_validates_capability_before_touching_the_provider(): void
    {
        $fake = $this->fakeProvider();
        [, $unit, , $token] = $this->roomWithDevice();
        $device = $this->light($unit);

        $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$device->id}/command", [
                'capability' => 'does_not_exist', 'value' => true,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $fake->commandCalls);
    }

    public function test_command_validates_value_range_before_touching_the_provider(): void
    {
        $fake = $this->fakeProvider();
        [, $unit, , $token] = $this->roomWithDevice();
        $device = $this->light($unit);

        $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$device->id}/command", [
                'capability' => 'brightness', 'value' => 5000,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $fake->commandCalls);
    }

    public function test_valid_command_reaches_the_provider_and_updates_state(): void
    {
        $fake = $this->fakeProvider();
        [, $unit, , $token] = $this->roomWithDevice();
        $device = $this->light($unit);

        $response = $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$device->id}/command", [
                'capability' => 'power', 'value' => true,
            ]);

        $response->assertOk();
        $this->assertSame(1, $fake->commandCalls);
        $this->assertSame(['power' => true], $fake->lastCommand);
        $this->assertTrue($device->fresh()->last_state['power']);
    }

    public function test_offline_device_rejects_command_server_side(): void
    {
        $fake = $this->fakeProvider();
        [, $unit, , $token] = $this->roomWithDevice();
        $device = $this->light($unit, ['status' => 'offline']);

        $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$device->id}/command", [
                'capability' => 'power', 'value' => true,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $fake->commandCalls);
    }

    public function test_provider_failure_returns_friendly_502_and_logs_failure(): void
    {
        $fake = $this->fakeProvider();
        $fake->throwOnCommand = true;
        [, $unit, , $token] = $this->roomWithDevice();
        $device = $this->light($unit);

        $response = $this->withToken($token)
            ->postJson("/api/v1/guest/room/devices/{$device->id}/command", [
                'capability' => 'power', 'value' => true,
            ]);

        $response->assertStatus(502);
        $this->assertStringNotContainsString('Tuya', $response->json('message'));
        $this->assertDatabaseHas('smart_device_activity_logs', [
            'smart_device_id' => $device->id,
            'event' => 'command_failed',
        ]);
    }

    public function test_scene_activation_fires_the_configured_sequence_of_commands(): void
    {
        $fake = $this->fakeProvider();
        [, $unit, , $token] = $this->roomWithDevice();
        $light = $this->light($unit);
        $ac = SmartDevice::create([
            'room_unit_id' => $unit->id, 'name' => 'AC', 'type' => 'ac', 'provider' => 'tuya',
            'provider_device_id' => 'tuya-'.Str::random(10),
            'capabilities' => ['power' => ['code' => 'switch', 'type' => 'bool'], 'temperature' => ['code' => 'temp_set', 'type' => 'int', 'min' => 16, 'max' => 30]],
            'status' => 'online', 'is_active' => true,
        ]);

        $scene = SmartScene::create(['room_unit_id' => $unit->id, 'name' => 'Welcome', 'slug' => 'welcome']);
        SmartSceneAction::create(['smart_scene_id' => $scene->id, 'smart_device_id' => $light->id, 'command' => ['power' => true], 'sort_order' => 1]);
        SmartSceneAction::create(['smart_scene_id' => $scene->id, 'smart_device_id' => $ac->id, 'command' => ['power' => true, 'temperature' => 22], 'sort_order' => 2]);

        $response = $this->withToken($token)->postJson("/api/v1/guest/room/scenes/{$scene->id}/activate");

        $response->assertOk();
        $this->assertSame(3, $fake->commandCalls); // 1 light action + 2 ac keys
        $this->assertTrue($light->fresh()->last_state['power']);
        $this->assertSame(22, $ac->fresh()->last_state['temperature']);
    }

    public function test_scene_from_another_room_is_rejected(): void
    {
        $this->fakeProvider();
        [, , , $token] = $this->roomWithDevice();
        [, $otherUnit] = $this->roomWithDevice();
        $scene = SmartScene::create(['room_unit_id' => $otherUnit->id, 'name' => 'Sleep', 'slug' => 'sleep']);

        $this->withToken($token)
            ->postJson("/api/v1/guest/room/scenes/{$scene->id}/activate")
            ->assertForbidden();
    }
}

/** In-memory fake — never touches the network, records what would be sent. */
class FakeSmartDeviceProvider implements SmartDeviceProviderInterface
{
    public int $commandCalls = 0;

    public array $lastCommand = [];

    public bool $throwOnCommand = false;

    public function discover(): array
    {
        return [];
    }

    public function status(SmartDevice $device): array
    {
        return [];
    }

    public function sendCommand(SmartDevice $device, string $capability, mixed $value): void
    {
        $this->commandCalls++;
        $this->lastCommand = [$capability => $value];

        if ($this->throwOnCommand) {
            throw new TuyaException('Tuya simulated failure.');
        }
    }
}
