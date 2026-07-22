<?php

namespace App\Services;

use RuntimeException;

/** Raised when the TTLock API is unreachable or returns an error. */
class TTLockException extends RuntimeException
{
    /**
     * @param  int|null  $errcode  The TTLock API "errcode" when the error came
     *                             from a logical API response (null for HTTP/
     *                             transport failures and local validation).
     */
    public function __construct(string $message = '', public ?int $errcode = null)
    {
        parent::__construct($message);
    }
}
