<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed roles, permissions and the default admin account.
     */
    public function run(): void
    {
        // Reset cached roles and permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions grouped by the admin modules the portal manages.
        $permissions = [
            'view dashboard',
            'manage bookings',
            'manage rooms',
            'manage users',
            'manage staff',
            'manage cms',
            'manage payments',
            'manage restaurant',
            'manage spa',
            'manage cinema',
            'manage gym',
            'manage transport',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles.
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

        // Super admin gets everything (also short-circuited via Gate::before if configured).
        $superAdmin->syncPermissions(Permission::all());

        // Admin gets everything except settings.
        $admin->syncPermissions(
            Permission::whereNot('name', 'manage settings')->get()
        );

        // Manager gets day-to-day operational permissions.
        $manager->syncPermissions([
            'view dashboard',
            'manage bookings',
            'manage rooms',
            'manage restaurant',
            'manage spa',
            'manage cinema',
            'manage gym',
            'manage transport',
        ]);

        // Default super-admin account for first login (temporary credentials).
        $user = User::updateOrCreate(
            ['email' => 'admin@retirodelrocio.ng'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin12345'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['super-admin']);
    }
}
