<?php

namespace App\Contracts;

/**
 * Interface for sending emails.
 */
interface MailerInterface
{
    /**
     * Send an email.
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody Email body in HTML format
     * 
     * @return bool True if the email was sent successfully, false otherwise
     */
    public function send(string $to, string $subject, string $htmlBody): bool;
}
