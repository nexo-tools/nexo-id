<?php

use App\Http\Middleware\EnforcePkceS256;
use App\Http\Middleware\NoStoreForSensitiveResponses;
use App\Http\Middleware\ThrottleAuthorizeByIp;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Passport routes. The first two only act on the
    | authorization endpoint and pass through elsewhere (token/jwks/userinfo):
    | ThrottleAuthorizeByIp caps requests per IP (flood protection, runs first);
    | EnforcePkceS256 rejects a 'plain' PKCE downgrade (S256 only).
    | NoStoreForSensitiveResponses acts on every one of them: nothing the
    | OAuth surface answers belongs in a browser or proxy cache (ADR-010).
    |
    | Nothing here may read the signed-in user: these run BEFORE the `web`
    | group of the authorize route (cookies still encrypted, session not
    | started), so $request->user() is always null. The checks that need the
    | session — RequireVerifiedForAuthorize and SignUpOnPromptCreate — live at
    | the end of the web group (bootstrap/app.php) for that reason.
    |
    */

    'middleware' => [
        ThrottleAuthorizeByIp::class,
        EnforcePkceS256::class,
        NoStoreForSensitiveResponses::class,
    ],

];
