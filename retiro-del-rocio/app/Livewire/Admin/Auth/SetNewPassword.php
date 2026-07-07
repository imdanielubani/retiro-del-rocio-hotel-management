<?php

namespace App\Livewire\Admin\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.admin.guest')]
class SetNewPassword extends Component
{
    /** The verification must have happened within this window. */
    public const VERIFY_WINDOW_SECONDS = 900;

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Ensure the visitor passed the verification step.
     */
    public function mount(): void
    {
        $this->email = (string) session('password_reset_verified_email', '');
        $verifiedAt = (int) session('password_reset_verified_at', 0);

        if ($this->email === '' || $verifiedAt === 0
            || (now()->timestamp - $verifiedAt) > self::VERIFY_WINDOW_SECONDS) {
            $this->redirectRoute('admin.password.request', navigate: true);
        }
    }

    /**
     * Validation rules mirror the on-screen password checklist.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:12',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 12 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.regex' => 'Password must include an uppercase letter, a number and a special character.',
        ];
    }

    /**
     * Persist the new password.
     */
    public function resetPassword(): void
    {
        $this->validate();

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->redirectRoute('admin.password.request', navigate: true);

            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Invalidate the code and clear the reset session state.
        DB::table('password_reset_tokens')->where('email', $this->email)->delete();
        session()->forget([
            'password_reset_email',
            'password_reset_verified_email',
            'password_reset_verified_at',
        ]);

        event(new PasswordReset($user));

        // Hand off to the success step.
        session(['password_reset_success_email' => $this->email]);

        $this->redirectRoute('admin.password.success', navigate: true);
    }

    public function render()
    {
        return view('admin.auth.set-new-password');
    }
}
