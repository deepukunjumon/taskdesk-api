<?php

namespace App\Providers;

use App\Contracts\OtpHasherInterface;
use App\Services\Otp\HashedOtpHasher;
use App\Services\Otp\PlainOtpHasher;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OtpHasherInterface::class,
            fn () => config('security.otp_hashing_enabled') ? new HashedOtpHasher() : new PlainOtpHasher(),
        );
    }
}
