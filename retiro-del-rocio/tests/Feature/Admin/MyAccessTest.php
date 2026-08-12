<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Access\MyAccess;
use App\Models\User;
use Database\Seeders\StaffRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Manager (and any other admin-portal role) can see exactly what they've
 * been granted, even though they can't reach the Roles & Permissions or
 * Users & Staff screens themselves.
 */
class MyAccessTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        foreach (['view dashboard', 'manage bookings', 'manage rooms', 'manage users', 'manage settings'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::whereNot('name', 'manage settings')->get());

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions(['view dashboard', 'manage bookings', 'manage rooms']);
    }

    private function manager(): User
    {
        $this->seedRoles();
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('manager');

        return $user;
    }

    public function test_a_manager_sees_their_own_role_and_permissions(): void
    {
        $this->actingAs($this->manager());

        Livewire::test(MyAccess::class)
            ->assertOk()
            ->assertSee('Manager')
            ->assertSee('view dashboard')
            ->assertSee('manage bookings')
            ->assertSee('manage rooms')
            ->assertDontSee('manage users')
            ->assertDontSee('manage settings');
    }

    public function test_a_manager_cannot_reach_the_roles_and_permissions_screen(): void
    {
        $this->actingAs($this->manager());

        $this->get(route('admin.access.roles'))->assertForbidden();
    }

    public function test_a_manager_cannot_reach_the_users_and_staff_screen(): void
    {
        $this->actingAs($this->manager());

        $this->get(route('admin.access.users'))->assertForbidden();
    }

    public function test_a_manager_can_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->manager());

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_a_super_admin_sees_every_permission_regardless_of_direct_grants(): void
    {
        $this->seedRoles();
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('super-admin');
        $this->actingAs($superAdmin);

        Livewire::test(MyAccess::class)
            ->assertOk()
            ->assertSee('Super Admin')
            ->assertSee('manage settings')
            ->assertSee('manage users');
    }

    public function test_the_manager_role_is_not_a_valid_staff_tablet_role(): void
    {
        $this->assertNotContains('manager', StaffRolesSeeder::ROLES);
    }
}
