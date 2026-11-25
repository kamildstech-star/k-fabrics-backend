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
        RateLimiter::for('auth-entry', function (Request $request): array {
            $identity = hash('sha256', strtolower(trim((string) $request->string('email'))) . '|' . $request->ip());

            return [
                Limit::perMinute(20)->by('storefront-auth-ip:' . $request->ip()),
                Limit::perMinute(5)->by('storefront-auth-login:' . $identity),
            ];
        });
    }
}
