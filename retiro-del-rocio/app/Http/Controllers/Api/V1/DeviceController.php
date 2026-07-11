<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Read-only device endpoints for staff clients (manager/IT tablet, tooling).
 * Authenticated with a USER Sanctum token and gated by DevicePolicy.
 */
class DeviceController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->ensureStaff($request);
        $this->authorize('viewAny', Device::class);

        $devices = Device::query()
            ->with(['type', 'room'])
            ->when($request->filled('type'), fn ($q) => $q->ofType($request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')))
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return DeviceResource::collection($devices);
    }

    public function show(Request $request, Device $device)
    {
        $this->ensureStaff($request);
        $this->authorize('view', $device);

        return new DeviceResource($device->load(['type', 'room']));
    }

    public function showByCode(Request $request, string $code)
    {
        $this->ensureStaff($request);

        $device = Device::with(['type', 'room'])->where('device_code', $code)->firstOrFail();
        $this->authorize('view', $device);

        return new DeviceResource($device);
    }

    private function ensureStaff(Request $request): void
    {
        abort_unless($request->user() instanceof User, 403, 'Staff access only.');
    }
}
