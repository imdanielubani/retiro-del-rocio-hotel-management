<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            // Identifies the token per device so it can be revoked individually.
            'device_name' => ['required', 'string', 'max:190'],
        ];
    }
}
