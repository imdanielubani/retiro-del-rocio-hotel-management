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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }
}
