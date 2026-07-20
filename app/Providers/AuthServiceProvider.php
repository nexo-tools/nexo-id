<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // OIDC + OAuth scopes exposed by the provider (openid, profile, email, …).
        Passport::tokensCan(config('openid.passport.tokens_can'));

        // Passport 13 needs the authorization view registered explicitly.
        Passport::authorizationView('auth.oauth.authorize');

        // Short-lived access tokens; longer refresh tokens (auth codes default
        // to Passport's 10-minute lifetime).
        Passport::tokensExpireIn(now()->addMinutes(60));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
