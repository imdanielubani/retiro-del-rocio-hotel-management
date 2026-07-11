<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * OTP-based password reset for the tablet app.
 *  1. sendOtp   — email a 6-digit code
 *  2. verifyOtp — check the code (optional pre-check step)
 *  3. reset     — set a new password with a valid code
 */
class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $data['email'])->first();

        // Only send to real accounts, but always respond the same (no enumeration).
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            Cache::put($this->key($user->email), [
                'hash' => Hash::make($otp),
                'attempts' => 0,
            ], now()->addMinutes(self::TTL_MINUTES));

            Mail::to($user->email)->send(new PasswordResetOtp($user, $otp, self::TTL_MINUTES));
        }

        return response()->json([
            'message' => 'If that email is registered, a reset code has been sent.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
        ]);

        $this->assertOtp($data['email'], $data['otp']);

        return response()->json(['message' => 'Code verified.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->assertOtp($data['email'], $data['otp']);
        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        Cache::forget($this->key($data['email']));

        return response()->json(['message' => 'Your password has been reset.']);
    }

    /** Validate the OTP; returns the user or throws a 422. */
    private function assertOtp(string $email, string $otp): User
    {
        $user = User::where('email', $email)->first();
        $record = Cache::get($this->key($email));

        $invalid = fn () => throw ValidationException::withMessages([
            'otp' => ['This code is invalid or has expired.'],
        ]);

        if (! $user || ! is_array($record)) {
            $invalid();
        }

        if (($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($email));
            throw ValidationException::withMessages([
                'otp' => ['Too many attempts. Please request a new code.'],
            ]);
        }

        if (! Hash::check($otp, $record['hash'])) {
            $record['attempts'] = ($record['attempts'] ?? 0) + 1;
            Cache::put($this->key($email), $record, now()->addMinutes(self::TTL_MINUTES));
            $invalid();
        }

        return $user;
    }

    private function key(string $email): string
    {
        return 'pwd_otp:'.mb_strtolower(trim($email));
    }
}
