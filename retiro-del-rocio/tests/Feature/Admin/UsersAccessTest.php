<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Access\Users;
use App\Models\User;
use Database\Seeders\DeviceManagementSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Users & Staff: full account management stays behind `manage users`
 * (Admin/Super Admin), but a Manager's own `reset credentials` permission
 * lets them reach this screen too — narrowly, to reset a staffer's tablet
 * password/PIN without touching their name, email, roles, or status.
 */
class UsersAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_a_super_admin_can_create_a_user_with_a_password_and_pin(): void
    {
        $this->actingAs($this->admin('super-admin'));

        Livewire::test(Users::class)
            ->call('openCreate')
            ->set('fName', 'New Staffer')
            ->set('fEmail', 'staffer@example.test')
            ->set('fPassword', 'Password123')
            ->set('fPin', '4821')
            ->set('fRoles', ['manager'])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'staffer@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertTrue(Hash::check('4821', $user->pin));
    }

    public function test_a_manager_cannot_open_the_add_user_form(): void
    {
        $this->actingAs($this->admin('manager'));

        Livewire::test(Users::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_a_manager_cannot_edit_a_users_roles_or_status(): void
    {
        $this->actingAs($this->admin('manager'));
        $staffer = User::factory()->create(['status' => 'active']);

        Livewire::test(Users::class)
            ->call('edit', $staffer->id)
            ->assertForbidden();

        Livewire::test(Users::class)
            ->call('toggleStatus', $staffer->id)
            ->assertForbidden();

        Livewire::test(Users::class)
            ->call('delete', $staffer->id)
            ->assertForbidden();
    }

    public function test_a_manager_can_reset_a_staffers_password_and_pin(): void
    {
        $this->actingAs($this->admin('manager'));
        $staffer = User::factory()->create(['status' => 'active', 'password' => Hash::make('OldPassword1')]);

        Livewire::test(Users::class)
            ->call('openReset', $staffer->id)
            ->assertOk()
            ->set('rPassword', 'NewPassword1')
            ->set('rPin', '1234')
            ->call('saveReset')
            ->assertHasNoErrors()
            ->assertSet('savedCredentials.password', 'NewPassword1')
            ->assertSet('savedCredentials.pin', '1234');

        $staffer->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $staffer->password));
        $this->assertTrue(Hash::check('1234', $staffer->pin));
    }

    public function test_resetting_a_pin_already_used_by_another_staffer_is_rejected(): void
    {
        $this->actingAs($this->admin('manager'));
        User::factory()->create(['status' => 'active', 'pin' => Hash::make('1234')]);
        $staffer = User::factory()->create(['status' => 'active']);

        Livewire::test(Users::class)
            ->call('openReset', $staffer->id)
            ->set('rPin', '1234')
            ->call('saveReset')
            ->assertHasErrors('rPin');

        $this->assertNull($staffer->fresh()->pin);
    }

    public function test_resetting_a_staffer_to_the_pin_they_already_have_does_not_falsely_collide_with_themselves(): void
    {
        $this->actingAs($this->admin('manager'));
        $staffer = User::factory()->create(['status' => 'active', 'pin' => Hash::make('1234')]);

        Livewire::test(Users::class)
            ->call('openReset', $staffer->id)
            ->set('rPin', '1234')
            ->call('saveReset')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('1234', $staffer->fresh()->pin));
    }

    public function test_resetting_credentials_requires_at_least_one_field(): void
    {
        $this->actingAs($this->admin('manager'));
        $staffer = User::factory()->create(['status' => 'active']);

        Livewire::test(Users::class)
            ->call('openReset', $staffer->id)
            ->call('saveReset')
            ->assertHasErrors('rPassword');
    }

    public function test_generate_buttons_fill_in_a_password_and_a_four_digit_pin(): void
    {
        $this->actingAs($this->admin('manager'));
        $staffer = User::factory()->create(['status' => 'active']);

        $component = Livewire::test(Users::class)
            ->call('openReset', $staffer->id)
            ->call('generatePassword')
            ->call('generatePin');

        $this->assertGreaterThanOrEqual(8, strlen($component->get('rPassword')));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $component->get('rPin'));
    }

    public function test_an_admin_portal_user_with_neither_permission_cannot_reach_the_screen(): void
    {
        // it-administrator reaches the admin portal (isAdmin()) but holds
        // only device/tablet permissions — neither manage users nor reset
        // credentials.
        $this->seed(DeviceManagementSeeder::class);
        $user = $this->admin('it-administrator');

        $this->actingAs($user);

        $this->get(route('admin.access.users'))->assertForbidden();
    }
}
