<?php

use App\Http\Controllers\Oidc\OpenIdConfigurationController;
use Illuminate\Support\Facades\Route;
use OpenIDConnect\Laravel\UserInfoController;

/*
|--------------------------------------------------------------------------
| OIDC route overrides
|--------------------------------------------------------------------------
|
| Loaded bare (no web/session group, mirroring the jeremy379/laravel-openid-
| connect bridge) via the withRouting `then` hook in bootstrap/app.php. These
| replace the bridge's built-in routes, which are disabled in config/openid.php
| so ours are the only registration.
|
*/

// Discovery document advertising S256-only PKCE (the bridge hardcodes 'plain').
Route::get('/.well-known/openid-configuration', OpenIdConfigurationController::class)
    ->name('openid.discovery');

// UserInfo with a per-IP ceiling (throttle:oidc-ip) on top of Passport's token
// guard. Framework middleware priority runs Authenticate before ThrottleRequests,
// so this caps authenticated traffic per IP (an abusive client token cannot
// hammer userinfo); token-less requests are already rejected cheaply by auth:api.
Route::get('/oauth/userinfo', UserInfoController::class)
    ->middleware(['auth:api', 'throttle:oidc-ip'])
    ->name('openid.userinfo');
