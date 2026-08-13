<?php

namespace App\Services\Mail;

use App\Contracts\MailerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pepipost mailer implementation.
 */
class PepipostMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $url = config('services.pepipost.url');
        $key = config('services.pepipost.key');

        try {
            $response = Http::withHeaders([
                'api_key' => $key,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'from' => [
                    'email' => config('mail.from.address'),
                    'name' => config('mail.from.name'),
                ],
                'subject' => $subject,
                'content' => [
                    ['type' => 'html', 'value' => $htmlBody],
                ],
                'personalizations' => [
                    ['to' => [['email' => $to]]],
                ],
            ]);

            if ($response->failed()) {
                Log::error('Pepipost mail send failed.', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Pepipost mail send threw an exception.', [
                'to' => $to,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
