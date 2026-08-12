<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DeviceManagementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar (components/admin/app.blade.php) mirrors each user's actual
 * permissions — a role that can't open a module shouldn't see its link
 * either. Renders the real admin dashboard end-to-end (not a Livewire unit
 * test) since the nav lives in the shared layout, not a single component.
 */
class SidebarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_manager_only_sees_modules_their_permissions_cover(): void
    {
        $this->actingAs($this->admin('manager'));

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        // Granted to manager — see RolesAndPermissionsSeeder.
        $response->assertSee('Apartments');
        $response->assertSee('Spa &amp; Wellness', false);
        // Not granted to manager — must be hidden, not just link-disabled.
        $response->assertDontSee('Housekeeping');
        $response->assertDontSee('Maintenance');
        $response->assertDontSee('Bar Inventory');
        $response->assertDontSee('Users &amp; Staff', false);
        $response->assertDontSee('Roles &amp; Permissions', false);
        $response->assertDontSee('Billing');
        $response->assertDontSee('Tablets');
    }

    public function test_a_manager_can_still_see_my_access(): void
    {
        $this->actingAs($this->admin('manager'));

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('My Access');
    }

    public function test_an_admin_sees_every_module_except_settings_and_roles(): void
    {
        $this->actingAs($this->admin('admin'));

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Housekeeping');
        $response->assertSee('Maintenance');
        $response->assertSee('Bar Inventory');
        $response->assertSee('Users &amp; Staff', false);
        $response->assertSee('Billing');
        $response->assertDontSee('Roles &amp; Permissions', false);
        $response->assertDontSee('Settings');
    }

    public function test_a_super_admin_sees_every_module_including_settings_and_roles(): void
    {
        $this->actingAs($this->admin('super-admin'));

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Housekeeping');
        $response->assertSee('Roles &amp; Permissions', false);
        $response->assertSee('Settings');
    }

    public function test_a_manager_typing_the_housekeeping_url_directly_is_blocked(): void
    {
        $this->actingAs($this->admin('manager'));

        $this->get(route('admin.housekeeping.room-status'))->assertForbidden();
    }

    public function test_an_it_administrator_can_still_land_on_the_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DeviceManagementSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('it-administrator');
        $this->actingAs($user);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tablets');
        $response->assertDontSee('Housekeeping');
    }
}
