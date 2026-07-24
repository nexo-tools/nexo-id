<?php

use App\Http\Middleware\EnforcePkceS256;
use App\Http\Middleware\RequireVerifiedForAuthorize;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Passport routes. Both only act on the
    | authorization endpoint and pass through elsewhere (token/jwks/userinfo):
    | EnforcePkceS256 rejects a 'plain' PKCE downgrade (S256 only);
    | RequireVerifiedForAuthorize gates it on a verified email (AC-AUTH-3).
    |
    */

    'middleware' => [
        EnforcePkceS256::class,
        RequireVerifiedForAuthorize::class,
    ],

];
