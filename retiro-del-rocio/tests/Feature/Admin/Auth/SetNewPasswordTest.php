<?php

namespace Tests\Feature\Admin\Auth;

use App\Livewire\Admin\Auth\SetNewPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SetNewPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function markVerified(string $email): void
    {
        session([
            'password_reset_verified_email' => $email,
            'password_reset_verified_at' => now()->timestamp,
        ]);
    }

    public function test_it_redirects_when_not_verified(): void
    {
        Livewire::test(SetNewPassword::class)
            ->assertRedirectToRoute('admin.password.request');
    }

    public function test_it_redirects_when_verification_expired(): void
    {
        session([
            'password_reset_verified_email' => 'admin@retirodelrocio.com',
            'password_reset_verified_at' => now()->subMinutes(16)->timestamp,
        ]);

        Livewire::test(SetNewPassword::class)
            ->assertRedirectToRoute('admin.password.request');
    }

    public function test_the_screen_renders_when_verified(): void
    {
        $this->markVerified('admin@retirodelrocio.com');

        Livewire::test(SetNewPassword::class)
            ->assertOk()
            ->assertSee('Create new password')
            ->assertSee('Minimum 12 characters');
    }

    public function test_a_strong_password_is_saved(): void
    {
        $user = User::factory()->create(['email' => 'admin@retirodelrocio.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => 'admin@retirodelrocio.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);
        $this->markVerified('admin@retirodelrocio.com');

        Livewire::test(SetNewPassword::class)
            ->set('password', 'NewStrongPass1!')
            ->set('password_confirmation', 'NewStrongPass1!')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('admin.password.success');

        $this->assertTrue(Hash::check('NewStrongPass1!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'admin@retirodelrocio.com']);
        $this->assertNull(session('password_reset_verified_email'));
        $this->assertSame('admin@retirodelrocio.com', session('password_reset_success_email'));
    }

    public function test_a_weak_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@retirodelrocio.com']);
        $this->markVerified('admin@retirodelrocio.com');

        Livewire::test(SetNewPassword::class)
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('resetPassword')
            ->assertHasErrors('password')
            ->assertNoRedirect();
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@retirodelrocio.com']);
        $this->markVerified('admin@retirodelrocio.com');

        Livewire::test(SetNewPassword::class)
            ->set('password', 'NewStrongPass1!')
            ->set('password_confirmation', 'Different1!')
            ->call('resetPassword')
            ->assertHasErrors(['password' => 'confirmed'])
            ->assertNoRedirect();
    }
}
