<?php

namespace App\Services\Otp;

use App\Contracts\OtpHasherInterface;
use Illuminate\Support\Facades\Hash;

/**
 * HashedOtpHasher implements OtpHasherInterface using Laravel's Hash facade.
 */
class HashedOtpHasher implements OtpHasherInterface
{
    public function hash(string $otp): string
    {
        return Hash::make($otp);
    }

    public function verify(string $otp, string $stored): bool
    {
        return Hash::check($otp, $stored);
    }
}
