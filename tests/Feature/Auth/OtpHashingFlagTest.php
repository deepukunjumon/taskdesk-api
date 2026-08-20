<?php

use App\Contracts\OtpHasherInterface;
use App\Jobs\SendPasswordResetOtp;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\Otp\HashedOtpHasher;
use App\Services\Otp\PlainOtpHasher;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    Queue::fake();
});

/**
 * Helper function to run the full forgot-password flow for a given email and return the stored OTP hash.
 *
 * @param string $email
 * @return string
 */
function otpHashingFlagTestRunFlow(string $email): string
{
    $service = app()->make(PasswordResetService::class);

    $service->requestOtp($email, '127.0.0.1');

    $capturedOtp = null;
    Queue::assertPushed(SendPasswordResetOtp::class, function (SendPasswordResetOtp $job) use ($email, &$capturedOtp) {
        if ($job->email !== $email) {
            return false;
        }
        $capturedOtp = $job->otp;

        return true;
    });

    $record = PasswordResetOtp::where('email', $email)->latest('created_at')->first();
    $storedHash = $record->otp_hash;

    $resetToken = $service->verifyOtp($email, $capturedOtp, '127.0.0.1');
    expect($resetToken)->toBeString()->not->toBeEmpty();

    return $storedHash;
}

it('binds HashedOtpHasher and succeeds when OTP_HASHING_ENABLED is true', function () {
    config(['security.otp_hashing_enabled' => true]);
    User::factory()->create(['email' => 'flagtrue@taskdesk.test']);

    expect(app()->make(OtpHasherInterface::class))->toBeInstanceOf(HashedOtpHasher::class);

    $storedHash = otpHashingFlagTestRunFlow('flagtrue@taskdesk.test');

    // bcrypt hash, not the plaintext OTP.
    expect($storedHash)->toStartWith('$2y$');
});

it('binds PlainOtpHasher and still succeeds when OTP_HASHING_ENABLED is false', function () {
    config(['security.otp_hashing_enabled' => false]);
    User::factory()->create(['email' => 'flagfalse@taskdesk.test']);

    expect(app()->make(OtpHasherInterface::class))->toBeInstanceOf(PlainOtpHasher::class);

    $storedHash = otpHashingFlagTestRunFlow('flagfalse@taskdesk.test');

    // 6-digit plaintext OTP, stored as-is.
    expect($storedHash)->toMatch('/^\d{6}$/');
});
