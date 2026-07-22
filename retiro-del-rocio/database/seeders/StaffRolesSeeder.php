<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff tablet roles. A staff tablet is locked to one of these; only a staff
 * account holding that role can log into it. `manager` already exists from
 * RolesAndPermissionsSeeder. Idempotent — safe to re-run on every deploy.
 *
 * These are NOT admin-portal roles: they do not grant web dashboard access
 * (see User::isAdmin()); they authenticate through the tablet/staff-login API.
 */
class StaffRolesSeeder extends Seeder
{
    /** The tablet-facing staff roles (excludes guest, and manager which exists). */
    public const ROLES = [
        'reception', 'kitchen', 'bar', 'housekeeping',
        'maintenance', 'security', 'cinema',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
