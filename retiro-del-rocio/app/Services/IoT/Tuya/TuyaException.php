<?php

namespace App\Services\IoT\Tuya;

use RuntimeException;

/** Raised when the Tuya Cloud API is unreachable or returns a logical error. */
class TuyaException extends RuntimeException
{
    /**
     * @param  string|null  $tuyaCode  Tuya's API "code" when the error came from
     *                                 a logical API response (null for HTTP/
     *                                 transport failures and local validation).
     */
    public function __construct(string $message = '', public ?string $tuyaCode = null)
    {
        parent::__construct($message);
    }
}
