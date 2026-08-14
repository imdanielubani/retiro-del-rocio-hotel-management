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

        // Permissions grouped by the admin modules the portal manages. Each
        // one gates both its sidebar link and its routes (see routes/web.php
        // and components/admin/app.blade.php) — a role without a permission
        // never sees or can open that module.
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
            'manage guests',
            'manage kitchen',
            'manage bar',
            'manage bar inventory',
            'manage housekeeping',
            'manage maintenance',
            'manage security',
            'manage billing',
            // Narrower than 'manage users' — lets a role reset any staff
            // member's tablet password/PIN from Users & Staff without
            // granting the rest of user management (creating accounts,
            // assigning roles, suspending, deleting).
            'reset credentials',
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

        // Manager gets day-to-day operational permissions only — every other
        // module (Kitchen, Bar, Housekeeping, Maintenance, Security, Devices,
        // Chat, CMS, Billing, etc.) stays hidden/blocked until a Super Admin
        // or Admin grants it from Roles & Permissions.
        $manager->syncPermissions([
            'view dashboard',
            'manage bookings',
            'manage rooms',
            'manage restaurant',
            'manage spa',
            'manage cinema',
            'manage gym',
            'manage transport',
            'reset credentials',
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
