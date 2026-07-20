<?php

use App\Http\Middleware\RequireVerifiedForAuthorize;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport route middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Passport routes. RequireVerifiedForAuthorize
    | only acts on the authorization endpoint (AC-AUTH-3); it passes through on
    | the token/jwks/userinfo routes.
    |
    */

    'middleware' => [
        RequireVerifiedForAuthorize::class,
    ],

];
