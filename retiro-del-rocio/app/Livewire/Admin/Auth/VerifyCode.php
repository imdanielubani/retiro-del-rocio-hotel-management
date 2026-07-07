<?php

namespace App\Livewire\Admin\Auth;

use App\Models\User;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.admin.guest')]
class VerifyCode extends Component
{
    /** Verification codes are valid for 10 minutes. */
    public const TTL_SECONDS = 600;

    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public int $expiresInSeconds = 0;

    /**
     * Resolve the pending reset email from the previous step.
     */
    public function mount(): void
    {
        $this->email = (string) session('password_reset_email', '');

        if ($this->email === '') {
            $this->redirectRoute('admin.password.request', navigate: true);

            return;
        }

        $this->expiresInSeconds = $this->remainingSeconds();
    }

    /**
     * Verify the submitted 6-digit code.
     */
    public function verify(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $row = DB::table('password_reset_tokens')->where('email', $this->email)->first();

        if (! $row) {
            $this->failWithError('Please request a new verification code.');
        }

        if (Carbon::parse($row->created_at)->addSeconds(self::TTL_SECONDS)->isPast()) {
            $this->failWithError('This code has expired. Please request a new one.');
        }

        if (! Hash::check($this->code, $row->token)) {
            RateLimiter::hit($this->throttleKey());

            $this->failWithError('The verification code is incorrect.');
        }

        RateLimiter::clear($this->throttleKey());

        // Mark this email as verified for the password reset step.
        session([
            'password_reset_verified_email' => $this->email,
            'password_reset_verified_at' => now()->timestamp,
        ]);

        $this->redirectRoute('admin.password.set', navigate: true);
    }

    /**
     * Resend a fresh verification code.
     */
    public function resend(): void
    {
        $key = 'resend-code|'.Str::lower($this->email);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'code' => __('Too many requests. Please wait before resending.'),
            ]);
        }

        RateLimiter::hit($key, 60);

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        if ($user = User::where('email', $this->email)->first()) {
            $user->notify(new SendPasswordResetCode($code));
        }

        $this->reset('code');
        $this->expiresInSeconds = self::TTL_SECONDS;

        $this->dispatch('code-resent');
    }

    /**
     * Add a code error and stop execution.
     */
    protected function failWithError(string $message): void
    {
        throw ValidationException::withMessages(['code' => __($message)]);
    }

    /**
     * Remaining lifetime of the current code, in seconds.
     */
    protected function remainingSeconds(): int
    {
        $row = DB::table('password_reset_tokens')->where('email', $this->email)->first();

        if (! $row || ! $row->created_at) {
            return 0;
        }

        $expiresAt = Carbon::parse($row->created_at)->addSeconds(self::TTL_SECONDS);

        return (int) max(0, now()->diffInSeconds($expiresAt, false));
    }

    /**
     * Ensure verification attempts are not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 6)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'code' => __('Too many attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        return 'verify-code|'.Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('admin.auth.verify-code', [
            'ttl' => self::TTL_SECONDS,
        ]);
    }
}
