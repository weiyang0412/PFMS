<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
        });
    }
}
