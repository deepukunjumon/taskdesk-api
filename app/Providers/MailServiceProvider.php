<?php

namespace App\Providers;

use App\Contracts\MailerInterface;
use App\Services\Mail\PepipostMailer;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        MailerInterface::class => PepipostMailer::class,
    ];
}
