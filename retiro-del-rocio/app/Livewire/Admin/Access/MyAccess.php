<?php

namespace App\Livewire\Admin\Access;

use Livewire\Component;
use Spatie\Permission\Models\Permission;

/**
 * Read-only self-service view of the signed-in admin's own roles and
 * permissions — what a Manager (who can't reach the Roles & Permissions
 * screen) uses to see exactly what they've been granted.
 */
class MyAccess extends Component
{
    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super-admin');

        $roles = $user->roles->pluck('name')->all();

        $permissions = $isSuperAdmin
            ? Permission::orderBy('name')->pluck('name')
            : $user->getAllPermissions()->pluck('name')->sort()->values();

        $grouped = $permissions->groupBy(function (string $name) {
            if (str_starts_with($name, 'manage ')) {
                return ucfirst(substr($name, 7));
            }

            return 'General';
        });

        return view('admin.access.my-access', [
            'roles' => $roles,
            'isSuperAdmin' => $isSuperAdmin,
            'grouped' => $grouped,
            'permissionCount' => $permissions->count(),
        ])->layout('components.admin.app', [
            'title' => 'My Access',
            'subtitle' => 'What your account can see and do',
        ]);
    }
}
