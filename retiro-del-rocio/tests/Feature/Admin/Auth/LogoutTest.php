<?php

namespace Tests\Feature\Admin\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
    }

    public function test_logging_out_signs_the_user_out_and_flashes_a_toast(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('toast', [
            'type' => 'success',
            'message' => 'You have been signed out successfully.',
        ]);

        $this->assertGuest();
    }

    public function test_the_login_screen_renders_the_flashed_toast(): void
    {
        $this->withSession(['toast' => [
            'type' => 'success',
            'message' => 'You have been signed out successfully.',
        ]])
            ->get(route('admin.login'))
            ->assertOk()
            ->assertSee('You have been signed out successfully.');
    }
}
