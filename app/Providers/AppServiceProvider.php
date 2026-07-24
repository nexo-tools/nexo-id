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
        // Per-IP ceilings. login POST already has a per-credential (email+IP)
        // lockout (LoginRequest); this adds a broad IP cap on top. userinfo has
        // no other limit — 'throttle:oidc-ip' is applied on its route. authorize
        // is capped by ThrottleAuthorizeByIp (Passport owns that route).
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
        RateLimiter::for('oidc-ip', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
    }
}
