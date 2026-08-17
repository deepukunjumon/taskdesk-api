<?php

namespace App\Services;

use App\Contracts\OtpHasherInterface;
use App\Exceptions\OtpVerificationException;
use App\Jobs\SendPasswordResetOtp;
use App\Models\PasswordResetOtp;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service for handling password reset requests, OTP generation/verification, and password updates.
 */
class PasswordResetService
{
    private const OTP_TTL_MINUTES = 10;

    private const RESET_TOKEN_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OtpHasherInterface $otpHasher,
    ) {}

    /**
     * Request a password reset OTP for the given email.
     * If the email does not match any account, logs the attempt but does not reveal this to the caller.
     */
    public function requestOtp(string $email, ?string $ip = null): void
    {
        $user = $this->users->findByEmail($email);

        if (! $user) {
            Log::info('Password reset requested for an email with no matching account.', [
                'email' => $email,
                'ip' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return;
        }

        // Only one active OTP per email at a time.
        PasswordResetOtp::where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetOtp::create([
            'email' => $email,
            'otp_hash' => $this->otpHasher->hash($otp),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        // No explicit ->afterCommit() here: this method opens no wrapping
        // transaction of its own, so there's nothing to defer past — and
        // relying on afterCommit would also mean the dispatch silently
        // never fires under test harnesses that wrap the whole test in an
        // outer transaction that's rolled back rather than committed.
        SendPasswordResetOtp::dispatch($email, $otp);
    }

    /**
     * @throws OtpVerificationException
     */
    public function verifyOtp(string $email, string $otp, ?string $ip = null): string
    {
        $record = PasswordResetOtp::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if (! $record) {
            $this->logFailedAttempt($email, $ip, 'no active OTP for this email');

            throw new OtpVerificationException('invalid', 'Invalid or expired code. Please request a new one.');
        }

        if ($record->hasExceededAttempts()) {
            $this->logFailedAttempt($email, $ip, 'attempt limit already reached');

            throw new OtpVerificationException('locked_out', 'Too many incorrect attempts. Please request a new code.');
        }

        if ($record->isExpired()) {
            $this->logFailedAttempt($email, $ip, 'expired');

            throw new OtpVerificationException('expired', 'This code has expired. Please request a new one.');
        }

        if (! $this->otpHasher->verify($otp, $record->otp_hash)) {
            $record->increment('attempts');
            $this->logFailedAttempt($email, $ip, 'incorrect code');

            if ($record->hasExceededAttempts()) {
                throw new OtpVerificationException('locked_out', 'Too many incorrect attempts. Please request a new code.');
            }

            $remaining = self::MAX_ATTEMPTS - $record->attempts;
            $plural = $remaining === 1 ? 'attempt' : 'attempts';
            throw new OtpVerificationException('invalid', "Incorrect code. {$remaining} {$plural} remaining.");
        }

        $resetToken = Str::random(64);

        $record->update([
            'consumed_at' => now(),
            // Hashed with a fast, deterministic digest (not the OTP hasher's
            // bcrypt) specifically so /reset-password can look this row up
            // by the hash directly — bcrypt's per-call salt makes that
            // impossible without iterating every row. Safe here because the
            // token itself is 64 random characters, not a 6-digit code.
            'reset_token_hash' => hash('sha256', $resetToken),
            'reset_token_expires_at' => now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ]);

        return $resetToken;
    }

    /**
     * @throws ValidationException
     */
    public function resetPassword(string $resetToken, string $newPassword): void
    {
        $record = PasswordResetOtp::where('reset_token_hash', hash('sha256', $resetToken))->first();

        if (! $record || ! $record->isResetTokenValid()) {
            throw ValidationException::withMessages([
                'reset_token' => ['This reset link has expired or already been used. Please start over.'],
            ]);
        }

        $user = $this->users->findByEmail($record->email);

        if (! $user) {
            throw ValidationException::withMessages([
                'reset_token' => ['This reset link has expired or already been used. Please start over.'],
            ]);
        }

        // `password` is cast `hashed` on the model, so the plain value here
        // is hashed automatically on save.
        $user->password = $newPassword;
        $user->save();

        $record->update(['reset_token_consumed_at' => now()]);
    }

    /** Never logs the OTP value itself — email, IP, timestamp, and reason only. */
    private function logFailedAttempt(string $email, ?string $ip, string $reason): void
    {
        Log::warning('Password reset OTP verification failed.', [
            'email' => $email,
            'ip' => $ip,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
