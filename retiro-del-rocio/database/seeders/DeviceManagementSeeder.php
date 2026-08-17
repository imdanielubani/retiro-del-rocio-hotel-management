<?php

namespace Database\Seeders;

use App\Models\DeviceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the Device Management module: device types, granular permissions and
 * the IT Administrator role. Idempotent (firstOrCreate) — safe to re-run and
 * safe to run alongside the existing RolesAndPermissionsSeeder.
 */
class DeviceManagementSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedDeviceTypes();
        $this->seedPermissionsAndRoles();
        $this->seedSmartRoomPermissions();
    }

    private function seedDeviceTypes(): void
    {
        $types = [
            ['name' => 'Tablet', 'description' => 'Guest & staff Android tablets.'],
            ['name' => 'Smart TV', 'description' => 'In-room smart televisions.'],
            ['name' => 'TTLock', 'description' => 'Smart door locks (keyless entry).'],
            ['name' => 'IoT Device', 'description' => 'Generic IoT / smart-room hardware.'],
        ];

        foreach ($types as $i => $type) {
            DeviceType::firstOrCreate(
                ['slug' => Str::slug($type['name'])],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'status' => 'active',
                    'sort_order' => $i,
                ],
            );
        }
    }

    private function seedPermissionsAndRoles(): void
    {
        $permissions = [
            'device.view', 'device.create', 'device.edit', 'device.delete',
            'device.assign', 'device.sync', 'device.restart', 'device.lock',
            'device.unlock', 'device.logs',
            'tv.view', 'tv.create', 'tv.edit', 'tv.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // New role for IT staff who manage the device fleet.
        $itAdmin = Role::firstOrCreate(['name' => 'it-administrator', 'guard_name' => 'web']);
        $itAdmin->givePermissionTo($permissions); // full device + tv control incl. provisioning

        // Super-admin passes via Gate::before, but grant explicitly too so the
        // route-level permission middleware never blocks it.
        if ($superAdmin = Role::where('name', 'super-admin')->first()) {
            $superAdmin->givePermissionTo($permissions);
        }

        // Existing roles get device access appropriate to their remit.
        // (super-admin is covered by the global Gate::before bypass.)
        if ($manager = Role::where('name', 'manager')->first()) {
            $manager->givePermissionTo(['device.view', 'device.logs', 'tv.view']); // read-only oversight
        }

        if ($admin = Role::where('name', 'admin')->first()) {
            // Operate devices, but provisioning (create/delete) stays with
            // super-admin + IT administrator per policy.
            $admin->givePermissionTo([
                'device.view', 'device.edit', 'device.assign', 'device.sync',
                'device.restart', 'device.lock', 'device.unlock', 'device.logs',
                'tv.view', 'tv.edit',
            ]);
        }
    }

    /**
     * Smart Room (Tuya) admin permissions — the it-administrator role already
     * manages hardware, so it gets full control; admin operates day-to-day,
     * manager gets read-only oversight, mirroring the device.* pattern above.
     */
    private function seedSmartRoomPermissions(): void
    {
        $permissions = [
            'smart-room.view', 'smart-room.manage', 'smart-room.sync',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        if ($itAdmin = Role::where('name', 'it-administrator')->first()) {
            $itAdmin->givePermissionTo($permissions);
        }

        if ($superAdmin = Role::where('name', 'super-admin')->first()) {
            $superAdmin->givePermissionTo($permissions);
        }

        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo($permissions);
        }

        if ($manager = Role::where('name', 'manager')->first()) {
            $manager->givePermissionTo(['smart-room.view']);
        }
    }
}
