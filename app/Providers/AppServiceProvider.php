<?php

namespace App\Providers;

use App\Auth\StartIdpSessionOnLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

        // Every web login starts an IdP session (sid) — ADR-010.
        Event::listen(Login::class, StartIdpSessionOnLogin::class);

        // Per-IP ceilings. login POST already has a per-credential (email+IP)
        // lockout (LoginRequest); this adds a broad IP cap on top. userinfo has
        // no other limit — 'throttle:oidc-ip' is applied on its route. authorize
        // is capped by ThrottleAuthorizeByIp (Passport owns that route).
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(
            (int) config('nexo.login_rate.per_ip')
        )->by((string) $request->ip()));
        RateLimiter::for('oidc-ip', fn (Request $request) => Limit::perMinute(
            (int) config('nexo.oidc_rate.per_ip')
        )->by((string) $request->ip()));
        // Sign-ups by IP, on a counter of their own. An inline throttle:10,1
        // keys by IP alone, the same counter as Passport's token endpoint
        // (plain `throttle`): the tools' token exchanges from one address spent
        // the sign-up budget and the third sign-up got a 429 (e2e, 2026-11-26).
        RateLimiter::for('register-ip', fn (Request $request) => Limit::perMinute(
            (int) config('nexo.register_rate.per_ip')
        )->by('register|'.$request->ip()));
    }
}
