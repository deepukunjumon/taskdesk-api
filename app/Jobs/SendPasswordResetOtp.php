<?php

namespace App\Jobs;

use App\Contracts\MailerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Job to send a password reset OTP email.
 */
class SendPasswordResetOtp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $email,
        public readonly string $otp,
    ) {}

    public function handle(MailerInterface $mailer): void
    {
        $htmlBody = view('emails.password-reset-otp', [
            'otp' => $this->otp,
        ])->render();

        $sent = $mailer->send(
            $this->email,
            'Your TaskDesk password reset code',
            $htmlBody,
        );


        if (! $sent) {
            throw new RuntimeException("Failed to send password-reset OTP email to {$this->email}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendPasswordResetOtp exhausted retries without a successful send.', [
            'email' => $this->email,
            'error' => $exception?->getMessage(),
        ]);
    }
}
