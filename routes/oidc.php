<?php

use App\Http\Controllers\Oidc\OpenIdConfigurationController;
use Illuminate\Support\Facades\Route;

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
