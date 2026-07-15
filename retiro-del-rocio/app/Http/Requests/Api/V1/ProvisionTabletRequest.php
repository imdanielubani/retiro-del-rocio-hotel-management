<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionTabletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authenticated by device_code + provision_token in the controller
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_code' => ['required', 'string', 'max:60'],
            // Pairing is QR-only: the token is the secret that authorises binding
            // a tablet to a suite. A device code alone is guessable and readable
            // off the dashboard, so it is never sufficient on its own.
            'provision_token' => ['required', 'string', 'max:64'],

            // Optional hardware/software details the tablet reports at bind time.
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'android_version' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'mac_address' => ['nullable', 'string', 'max:40'],
        ];
    }
}
