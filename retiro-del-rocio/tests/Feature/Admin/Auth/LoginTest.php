<?php

namespace Tests\Feature\Admin\Auth;

use App\Livewire\Admin\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('manager', 'web');
    }

    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_the_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSeeLivewire(Login::class);
    }

    public function test_an_admin_can_authenticate_and_is_sent_to_the_dashboard_with_a_toast(): void
    {
        $user = $this->admin(['name' => 'Super Admin', 'email' => 'admin@retirodelrocio.com']);

        Livewire::test(Login::class)
            ->set('email', 'admin@retirodelrocio.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame([
            'type' => 'success',
            'message' => 'Welcome back, Super Admin!',
        ], session('toast'));
    }

    public function test_a_user_without_an_admin_role_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'guest@retirodelrocio.com',
            'status' => 'active',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'guest@retirodelrocio.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_inactive_admin_cannot_login(): void
    {
        $this->admin(['email' => 'inactive@retirodelrocio.com', 'status' => 'inactive']);

        Livewire::test(Login::class)
            ->set('email', 'inactive@retirodelrocio.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->admin(['email' => 'admin@retirodelrocio.com']);

        Livewire::test(Login::class)
            ->set('email', 'admin@retirodelrocio.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_dashboard_renders_the_welcome_toast(): void
    {
        $user = $this->admin(['name' => 'Super Admin', 'email' => 'admin@retirodelrocio.com']);

        $this->actingAs($user)
            ->withSession(['toast' => ['type' => 'success', 'message' => 'Welcome back, Super Admin!']])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Super Admin!');
    }
}
