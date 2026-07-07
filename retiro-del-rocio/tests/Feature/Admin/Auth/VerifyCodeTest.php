<?php

namespace Tests\Feature\Admin\Auth;

use App\Livewire\Admin\Auth\VerifyCode;
use App\Models\User;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class VerifyCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCode(string $email, string $code, ?string $createdAt = null): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => $createdAt ?? now()],
        );
    }

    public function test_it_redirects_to_step_one_without_a_pending_email(): void
    {
        Livewire::test(VerifyCode::class)
            ->assertRedirectToRoute('admin.password.request');
    }

    public function test_the_screen_renders_with_a_pending_email(): void
    {
        session(['password_reset_email' => 'admin@retirodelrocio.com']);

        Livewire::test(VerifyCode::class)
            ->assertOk()
            ->assertSee('Enter verification Code')
            ->assertSee('admin@retirodelrocio.com')
            ->assertSet('email', 'admin@retirodelrocio.com');
    }

    public function test_a_correct_code_is_verified(): void
    {
        session(['password_reset_email' => 'admin@retirodelrocio.com']);
        $this->seedCode('admin@retirodelrocio.com', '123456');

        Livewire::test(VerifyCode::class)
            ->set('code', '123456')
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('admin.password.set');

        $this->assertSame('admin@retirodelrocio.com', session('password_reset_verified_email'));
    }

    public function test_an_incorrect_code_is_rejected(): void
    {
        session(['password_reset_email' => 'admin@retirodelrocio.com']);
        $this->seedCode('admin@retirodelrocio.com', '123456');

        Livewire::test(VerifyCode::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code')
            ->assertNoRedirect();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        session(['password_reset_email' => 'admin@retirodelrocio.com']);
        $this->seedCode('admin@retirodelrocio.com', '123456', now()->subMinutes(11));

        Livewire::test(VerifyCode::class)
            ->set('code', '123456')
            ->call('verify')
            ->assertHasErrors('code')
            ->assertNoRedirect();
    }

    public function test_a_non_six_digit_code_fails_validation(): void
    {
        session(['password_reset_email' => 'admin@retirodelrocio.com']);
        $this->seedCode('admin@retirodelrocio.com', '123456');

        Livewire::test(VerifyCode::class)
            ->set('code', '12a')
            ->call('verify')
            ->assertHasErrors(['code' => 'digits']);
    }

    public function test_resend_issues_a_new_code(): void
    {
        Notification::fake();

        session(['password_reset_email' => 'admin@retirodelrocio.com']);
        $user = User::factory()->create(['email' => 'admin@retirodelrocio.com']);
        $this->seedCode('admin@retirodelrocio.com', '123456');

        Livewire::test(VerifyCode::class)
            ->call('resend')
            ->assertHasNoErrors()
            ->assertSet('code', '');

        Notification::assertSentTo($user, SendPasswordResetCode::class);
    }
}
