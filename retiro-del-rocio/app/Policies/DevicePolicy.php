<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/**
 * Authorises staff (user-token) access to device endpoints. Auto-discovered by
 * Laravel for the Device model. Super-admin passes via the global Gate::before.
 */
class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('device.view');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can('device.view');
    }

    public function restart(User $user, Device $device): bool
    {
        return $user->can('device.restart');
    }

    public function lock(User $user, Device $device): bool
    {
        return $user->can('device.lock');
    }

    public function unlock(User $user, Device $device): bool
    {
        return $user->can('device.unlock');
    }
}
