<?php

namespace App\Livewire\Admin\Auth;

use App\Models\User;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.admin.guest')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    /**
     * Generate a 6-digit verification code and email it to the admin.
     */
    public function sendCode(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        RateLimiter::hit($this->throttleKey());

        $code = (string) random_int(100000, 999999);

        // Store the hashed code; reused on the verification step.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        // Only notify a real account, but never reveal whether it exists.
        $user = User::where('email', $this->email)->first();

        if ($user) {
            $user->notify(new SendPasswordResetCode($code));
        }

        // Carry the email to the verification step (never reveals existence).
        session(['password_reset_email' => $this->email]);

        $this->redirectRoute('admin.password.verify', navigate: true);
    }

    /**
     * Ensure the request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('Too many attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        return 'password-code|'.Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('admin.auth.forgot-password');
    }
}
