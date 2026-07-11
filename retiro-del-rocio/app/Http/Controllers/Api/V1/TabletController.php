<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HeartbeatRequest;
use App\Http\Requests\Api\V1\ProvisionTabletRequest;
use App\Http\Requests\Api\V1\StaffLoginRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceCommandService;
use App\Services\JwtService;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tablet-facing API. `provision` is public (validated by the QR's provision
 * token); `heartbeat`/`sync` are called by the tablet with its own device
 * token; the command endpoints are called by staff with a user token.
 */
class TabletController extends Controller
{
    use AuthorizesRequests;

    /** POST /tablets/provision — bind a tablet using its QR payload. */
    public function provision(ProvisionTabletRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hasToken = ! empty($data['provision_token']);

        $query = Device::where('device_code', $data['device_code']);
        if ($hasToken) {
            $query->where('provision_token', $data['provision_token']);
        }
        $device = $query->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'device_code' => ['Invalid device code or provisioning token.'],
            ]);
        }

        // The typed setup-code flow (no token) may only pair a device that is
        // still awaiting provisioning. Re-pairing an active tablet needs the QR.
        if (! $hasToken && $device->is_provisioned) {
            throw ValidationException::withMessages([
                'device_code' => ['This device is already paired. Use its QR code to re-pair it.'],
            ]);
        }

        // Re-provisioning replaces the device: drop any previous device tokens.
        $device->tokens()->delete();

        $device->forceFill([
            'is_provisioned' => true,
            'provisioned_at' => now(),
            'status' => DeviceStatus::Online,
            'last_seen_at' => now(),
            'serial_number' => $data['serial_number'] ?? $device->serial_number,
            'manufacturer' => $data['manufacturer'] ?? $device->manufacturer,
            'model' => $data['model'] ?? $device->model,
            'android_version' => $data['android_version'] ?? $device->android_version,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'mac_address' => $data['mac_address'] ?? $device->mac_address,
            'ip_address' => $request->ip(),
        ])->save();

        $device->log('provision', 'Device provisioned via QR.', ['ip' => $request->ip()]);

        $token = $device->createToken('tablet:'.$device->device_code, ['device'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device' => new DeviceResource($device->load(['type', 'room', 'roomUnit'])),
        ]);
    }

    /**
     * POST /tablets/staff-login — a staffer signs into a STAFF tablet. The
     * tablet is identified by its device token; the staffer must hold the role
     * the tablet is locked to. Returns the staffer's own user token + role.
     */
    public function staffLogin(StaffLoginRequest $request): JsonResponse
    {
        $device = $this->device($request);

        if (! $device->isStaff() || ! $device->role) {
            return response()->json(['message' => 'This tablet is not a staff station.'], 422);
        }

        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status && $user->status !== 'active') {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        if (! $user->hasRole($device->role)) {
            throw ValidationException::withMessages([
                'email' => ['This account is not authorised for the '.$device->role.' tablet.'],
            ]);
        }

        $device->log('login', $user->name.' signed in on the '.$device->role.' tablet.');

        // Issue a short-lived JWT (TTL from config/jwt.php → .env). Its exp claim
        // drives the tablet app's session-expiring warning + timeout.
        $jwt = app(JwtService::class)->issue([
            'sub' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $device->role,
            'roles' => $user->getRoleNames()->values()->all(),
            'device' => $device->device_code,
        ]);

        return response()->json([
            'token' => $jwt['token'],
            'token_type' => 'Bearer',
            'expires_in' => $jwt['expires_in'],
            'expires_at' => \Illuminate\Support\Carbon::createFromTimestamp($jwt['expires_at'])->toIso8601String(),
            'role' => $device->role,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values(),
            ],
        ]);
    }

    /** POST /tablets/heartbeat — telemetry from the tablet (device token). */
    public function heartbeat(HeartbeatRequest $request): JsonResponse
    {
        $device = $this->device($request);
        $data = $request->validated();

        $device->forceFill([
            'battery_level' => $data['battery_level'] ?? $device->battery_level,
            'wifi_strength' => $data['wifi_strength'] ?? $device->wifi_strength,
            'app_version' => $data['app_version'] ?? $device->app_version,
            'ip_address' => $data['ip_address'] ?? $request->ip(),
            'status' => $data['status'] ?? DeviceStatus::Online->value,
            'last_seen_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'device' => new DeviceResource($device)]);
    }

    /** POST /tablets/sync — device confirms a sync and collects pending commands. */
    public function sync(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $since = $device->last_sync_at;

        $commands = $device->activityLogs()
            ->whereIn('event', ['restart', 'lock', 'unlock'])
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->latest()
            ->limit(20)
            ->get(['event', 'created_at']);

        $device->forceFill([
            'last_sync_at' => now(),
            'last_seen_at' => now(),
            'status' => DeviceStatus::Online,
        ])->save();

        $device->log('sync', 'Device synced.');

        return response()->json([
            'ok' => true,
            'commands' => $commands->map(fn ($c) => [
                'command' => $c->event,
                'issued_at' => $c->created_at->toIso8601String(),
            ])->values(),
            'device' => new DeviceResource($device),
        ]);
    }

    /**
     * GET /tablets/room-status — the tablet's current room occupancy and, when
     * a guest is checked in, their booking details. Drives the guest welcome.
     */
    public function roomStatus(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $unit = $device->roomUnit()->with(['room', 'booking'])->first();

        if (! $unit) {
            return response()->json(['data' => [
                'occupancy' => 'unassigned',
                'suite_name' => null,
                'room_number' => null,
                'guest' => null,
            ]]);
        }

        $booking = $unit->status === 'occupied' ? $unit->booking : null;

        return response()->json(['data' => [
            'occupancy' => $unit->status, // available | occupied | maintenance
            'suite_name' => optional($unit->room)->name,
            'room_number' => $unit->number,
            'guest' => $booking ? [
                'name' => $booking->customer_name,
                'check_in' => optional($booking->check_in)->toDateString(),
                'check_out' => optional($booking->check_out)->toDateString(),
            ] : null,
        ]]);
    }

    public function restart(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'restart', fn (Device $d) => $commands->restart($d));
    }

    public function lock(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'lock', fn (Device $d) => $commands->lock($d));
    }

    public function unlock(Request $request, DeviceCommandService $commands): JsonResponse
    {
        return $this->command($request, 'unlock', fn (Device $d) => $commands->unlock($d));
    }

    /** Shared staff-command handler: validate, authorize via policy, record. */
    private function command(Request $request, string $ability, Closure $run): JsonResponse
    {
        abort_unless($request->user() instanceof User, 403, 'Staff access only.');

        $data = $request->validate(['device_code' => ['required', 'string', 'max:60']]);
        $device = Device::where('device_code', $data['device_code'])->firstOrFail();

        $this->authorize($ability, $device);
        $run($device);

        return response()->json([
            'ok' => true,
            'message' => ucfirst($ability).' command queued for '.$device->device_code.'.',
        ]);
    }

    private function device(Request $request): Device
    {
        $device = $request->user();
        abort_unless($device instanceof Device, 403, 'Device token required.');

        return $device;
    }
}
