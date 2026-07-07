<?php

namespace Tests\Feature\Admin\Auth;

use App\Livewire\Admin\Auth\ResetSuccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResetSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redirects_to_login_without_the_success_flag(): void
    {
        Livewire::test(ResetSuccess::class)
            ->assertRedirectToRoute('admin.login');
    }

    public function test_it_shows_the_verified_account_details(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@retirodelrocio.com',
        ]);
        session(['password_reset_success_email' => 'admin@retirodelrocio.com']);

        Livewire::test(ResetSuccess::class)
            ->assertOk()
            ->assertSee('Email verified!')
            ->assertSee('Super Admin')
            ->assertSee('admin@retirodelrocio.com')
            ->assertSet('name', 'Super Admin');

        // The success flag is single-use.
        $this->assertNull(session('password_reset_success_email'));
    }
}
