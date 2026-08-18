<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SmartDeviceProviderInterface;
use App\Events\SmartDeviceStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SmartDevice;
use App\Models\SmartScene;
use App\Services\IoT\Tuya\TuyaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Guest-facing Smart Room API — Sanctum device token only. Every method
 * derives `room_unit_id` from the authenticated Device (never from client
 * input) and rejects any cross-room access on `{smartDevice}`/`{scene}`
 * route-model binding. See docs/architecture/02-smart-room-architecture.md §API.
 */
class SmartRoomController extends Controller
{
    /** GET /guest/room/devices */
    public function devices(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $roomUnitId = $this->roomUnitId($device);

        $smartDevices = SmartDevice::query()
            ->where('room_unit_id', $roomUnitId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $smartDevices->map(fn (SmartDevice $d) => $this->deviceArray($d))->values()]);
    }

    /** GET /guest/room/devices/{smartDevice} */
    public function deviceShow(Request $request, SmartDevice $smartDevice): JsonResponse
    {
        $device = $this->device($request);
        $this->authorizeRoomMatch($smartDevice, $device);

        return response()->json(['data' => $this->deviceArray($smartDevice)]);
    }

    /** POST /guest/room/devices/{smartDevice}/command */
    public function command(Request $request, SmartDevice $smartDevice): JsonResponse
    {
        $device = $this->device($request);
        $this->authorizeRoomMatch($smartDevice, $device);

        $data = $request->validate([
            'capability' => ['required', 'string'],
            'value' => ['required'],
        ]);

        abort_unless($smartDevice->is_active, 422, 'This device is disabled.');
        abort_unless($smartDevice->hasCapability($data['capability']), 422, 'This device does not support that control.');
        abort_unless($smartDevice->valueIsValidFor($data['capability'], $data['value']), 422, 'That value is not valid for this control.');

        // Reject a command against a device we already know is offline —
        // client-side the UI disables the control too, this is the
        // server-side re-check per docs/architecture/03-tuya-architecture.md §7.
        abort_if($smartDevice->status === 'offline', 422, 'This device is currently offline.');

        try {
            $provider = app(SmartDeviceProviderInterface::class, ['provider' => $smartDevice->provider]);
            $provider->sendCommand($smartDevice, $data['capability'], $data['value']);
        } catch (TuyaException $e) {
            $smartDevice->log('command_failed', $e->getMessage(), [
                'capability' => $data['capability'],
                'value' => $data['value'],
            ]);

            return response()->json(['message' => $this->friendlyUnavailableMessage($smartDevice)], 502);
        }

        $newState = array_merge((array) $smartDevice->last_state, [$data['capability'] => $data['value']]);
        $smartDevice->forceFill(['last_state' => $newState])->save();
        $smartDevice->log('command_sent', null, ['capability' => $data['capability'], 'value' => $data['value']]);

        try {
            broadcast(SmartDeviceStatusChanged::forDevice($smartDevice));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->deviceArray($smartDevice->fresh())]);
    }

    /** GET /guest/room/scenes */
    public function scenes(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $roomUnit = $device->roomUnit()->first();
        abort_unless($roomUnit, 404, 'No room assigned to this device.');

        $scenes = SmartScene::query()
            ->forRoomUnit($roomUnit)
            ->active()
            ->ordered()
            ->get()
            // Room-level scenes take precedence over a same-slug category template.
            ->unique(fn (SmartScene $s) => $s->slug, false)
            ->sortBy(fn (SmartScene $s) => $s->room_unit_id ? 0 : 1);

        return response()->json(['data' => $scenes->values()->map(fn (SmartScene $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'icon' => $s->icon,
        ])]);
    }

    /** POST /guest/room/scenes/{scene}/activate */
    public function activateScene(Request $request, SmartScene $scene): JsonResponse
    {
        $device = $this->device($request);
        $roomUnit = $device->roomUnit()->first();
        abort_unless($roomUnit, 404, 'No room assigned to this device.');

        $applies = $scene->room_unit_id === $roomUnit->id || $scene->room_id === $roomUnit->room_id;
        abort_unless($applies, 403, 'This scene is not available for your room.');

        $failures = [];

        foreach ($scene->actions()->with('device')->get() as $action) {
            $smartDevice = $action->device;

            if (! $smartDevice || $smartDevice->room_unit_id !== $roomUnit->id || ! $smartDevice->is_active) {
                continue;
            }

            foreach ((array) $action->command as $capability => $value) {
                if (! $smartDevice->valueIsValidFor($capability, $value)) {
                    continue;
                }

                try {
                    $provider = app(SmartDeviceProviderInterface::class, ['provider' => $smartDevice->provider]);
                    $provider->sendCommand($smartDevice, $capability, $value);

                    $smartDevice->forceFill([
                        'last_state' => array_merge((array) $smartDevice->last_state, [$capability => $value]),
                    ])->save();

                    try {
                        broadcast(SmartDeviceStatusChanged::forDevice($smartDevice));
                    } catch (Throwable $e) {
                        report($e);
                    }
                } catch (TuyaException $e) {
                    $failures[] = $smartDevice->name;
                    $smartDevice->log('command_failed', $e->getMessage(), ['capability' => $capability, 'value' => $value, 'scene' => $scene->slug]);
                }
            }
        }

        SmartDevice::where('room_unit_id', $roomUnit->id)->first()?->log('scene_activated', $scene->name, ['scene_id' => $scene->id]);

        if ($failures) {
            return response()->json([
                'message' => 'Scene applied, but some devices could not be reached.',
                'failed_devices' => $failures,
            ], 207);
        }

        return response()->json(['ok' => true]);
    }

    /* --------------------------------------------------------------------- */
    /* Helpers */
    /* --------------------------------------------------------------------- */

    private function device(Request $request): Device
    {
        $device = $request->user();
        abort_unless($device instanceof Device, 403, 'Device token required.');

        return $device;
    }

    private function roomUnitId(Device $device): int
    {
        abort_unless($device->room_unit_id, 404, 'No room assigned to this device.');

        return (int) $device->room_unit_id;
    }

    /** Mirrors Device::currentBooking()'s discipline: never trust a loaded relation. */
    private function authorizeRoomMatch(SmartDevice $smartDevice, Device $device): void
    {
        $roomUnitId = $this->roomUnitId($device);
        abort_unless($smartDevice->room_unit_id === $roomUnitId, 403, 'This device is not in your room.');
    }

    /** @return array<string, mixed> */
    private function deviceArray(SmartDevice $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'type' => $d->type,
            'status' => $d->status,
            'capabilities' => $d->capabilities ?? [],
            'state' => $d->last_state ?? [],
        ];
    }

    private function friendlyUnavailableMessage(SmartDevice $d): string
    {
        return match ($d->type) {
            'ac' => 'Air conditioner is currently unavailable.',
            'light' => 'Lighting is currently unavailable.',
            'curtain' => 'Curtains are currently unavailable.',
            'tv' => 'Television is currently unavailable.',
            default => 'Smart Room is temporarily unavailable.',
        };
    }
}
