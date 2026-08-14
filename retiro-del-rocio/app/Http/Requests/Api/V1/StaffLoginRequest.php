<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the device token (auth:sanctum) on the route
    }

    /**
     * Exactly one of `password` or `pin` is required — whichever tab the
     * staffer picked on the login screen. A PIN sign-in needs no email at
     * all — the PIN alone identifies the staffer (scoped to the tablet's
     * own role); email is only required alongside a password.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required_with:password', 'nullable', 'email', 'max:190'],
            'password' => ['required_without:pin', 'nullable', 'string'],
            'pin' => ['required_without:password', 'nullable', 'string', 'digits:4'],
        ];
    }
}
