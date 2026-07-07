<?php

namespace App\Services;

use RuntimeException;

/** Raised when the TTLock API is unreachable or returns an error. */
class TTLockException extends RuntimeException {}
