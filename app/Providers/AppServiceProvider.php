<?php

namespace App\Providers;

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
        config([
            'mail.default' => env('MAIL_MAILER', 'resend'),
            'mail.from.address' => env('MAIL_FROM_ADDRESS', 'noreply@getkhatmah.com'),
            'mail.from.name' => env('MAIL_FROM_NAME', 'ختمة'),
        ]);
    }
}
