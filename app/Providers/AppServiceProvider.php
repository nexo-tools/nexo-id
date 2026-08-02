<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
        // The family mail layout lives under resources/views/emails/ rather than
        // resources/views/components/ because that is where hex literals are
        // allowed (NoHardcodedColorsTest) — and a mail needs them: clients strip
        // <style> and know nothing about the design tokens. This line gives it
        // the normal component syntax: <x-nexo-mail::layout>.
        Blade::anonymousComponentPath(resource_path('views/emails/nexo'), 'nexo-mail');

        // Per-IP ceilings. login POST already has a per-credential (email+IP)
        // lockout (LoginRequest); this adds a broad IP cap on top. userinfo has
        // no other limit — 'throttle:oidc-ip' is applied on its route. authorize
        // is capped by ThrottleAuthorizeByIp (Passport owns that route).
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
        RateLimiter::for('oidc-ip', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
    }
}
