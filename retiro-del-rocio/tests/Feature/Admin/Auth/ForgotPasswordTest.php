<?php

namespace Tests\Feature\Admin\Auth;

use App\Livewire\Admin\Auth\ForgotPassword;
use App\Models\User;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forgot_password_screen_can_be_rendered(): void
    {
        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('Send Verification Code')
            ->assertSeeLivewire(ForgotPassword::class);
    }

    public function test_a_verification_code_is_sent_to_an_existing_admin(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'admin@retirodelrocio.com']);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'admin@retirodelrocio.com')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('admin.password.verify');

        Notification::assertSentTo($user, SendPasswordResetCode::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin@retirodelrocio.com',
        ]);

        $this->assertSame('admin@retirodelrocio.com', session('password_reset_email'));
    }

    public function test_email_is_required_and_must_be_valid(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('email', 'not-an-email')
            ->call('sendCode')
            ->assertHasErrors(['email' => 'email'])
            ->assertNoRedirect();
    }

    public function test_it_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nobody@retirodelrocio.com')
            ->call('sendCode')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('admin.password.verify');

        Notification::assertNothingSent();
    }
}
