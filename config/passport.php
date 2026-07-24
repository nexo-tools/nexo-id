<?php

use App\Http\Middleware\EnforcePkceS256;
use App\Http\Middleware\RequireVerifiedForAuthorize;
use App\Http\Middleware\ThrottleAuthorizeByIp;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Passport routes. All three only act on the
    | authorization endpoint and pass through elsewhere (token/jwks/userinfo):
    | ThrottleAuthorizeByIp caps requests per IP (flood protection, runs first);
    | EnforcePkceS256 rejects a 'plain' PKCE downgrade (S256 only);
    | RequireVerifiedForAuthorize gates it on a verified email (AC-AUTH-3).
    |
    */

    'middleware' => [
        ThrottleAuthorizeByIp::class,
        EnforcePkceS256::class,
        RequireVerifiedForAuthorize::class,
    ],

];
