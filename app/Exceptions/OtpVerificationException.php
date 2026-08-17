<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when OTP verification fails.
 */
class OtpVerificationException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
