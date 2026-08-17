<?php

namespace App\Contracts;

/**
 * Interface for OTP hashing and verification.
 */
interface OtpHasherInterface
{
    /**
     * Hash a plaintext OTP.
     */
    public function hash(string $otp): string;

    /**
     * Verify a plaintext OTP against a stored hash.
     */
    public function verify(string $otp, string $stored): bool;
}
