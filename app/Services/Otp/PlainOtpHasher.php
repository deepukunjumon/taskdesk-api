<?php

namespace App\Services\Otp;

use App\Contracts\OtpHasherInterface;

/*
* PlainOtpHasher implements OtpHasherInterface without hashing, storing the OTP in plaintext.
*/
class PlainOtpHasher implements OtpHasherInterface
{
    public function hash(string $otp): string
    {
        return $otp;
    }

    public function verify(string $otp, string $stored): bool
    {
        return hash_equals($stored, $otp);
    }
}
