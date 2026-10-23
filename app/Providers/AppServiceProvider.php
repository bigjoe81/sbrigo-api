<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('register', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perHour(3)->by('register:email:'.$email),
                Limit::perHour(10)->by('register:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-send', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(15, 3)->by('otp-send:email:'.$email),
                Limit::perHour(20)->by('otp-send:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(15, 5)->by('otp-verify:email:'.$email),
                Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip()),
            ];
        });
    }
}
