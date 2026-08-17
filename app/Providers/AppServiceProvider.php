<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        RateLimiter::for('forgot-password', function (Request $request) {
            return [
                Limit::perHour(3)->by('forgot-password-ip:'.$request->ip()),
                Limit::perHour(3)->by('forgot-password-email:'.strtolower((string) $request->input('email'))),
            ];
        });
    }
}
