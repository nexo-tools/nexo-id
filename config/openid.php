<?php

use Lcobucci\JWT\Signer\Rsa\Sha256;
use OpenIDConnect\Repositories\IdentityRepository;

return [
    'passport' => [

        /**
         * Place your Passport and OpenID Connect scopes here.
         * To receive an `id_token, you should at least provide the openid scope.
         */
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect',
            'profile' => 'Information about your profile',
            'email' => 'Information about your email address',
            // phone/address are omitted: IdentityEntity backs no such claims, so
            // advertising them (discovery scopes_supported / consent) would be a lie.
        ],
    ],

    /**
     * Place your custom claim sets here.
     */
    'custom_claim_sets' => [
        // 'login' => [
        //     'last-login',
        // ],
        // 'company' => [
        //     'company_name',
        //     'company_address',
        //     'company_phone',
        //     'company_email',
        // ],
    ],

    /**
     * You can override the repositories below.
     */
    'repositories' => [
        'identity' => IdentityRepository::class,
    ],

    'routes' => [
        /**
         * The bridge's discovery route is disabled here and re-registered in
         * routes/oidc.php (App\Http\Controllers\Oidc\OpenIdConfigurationController)
         * so the document advertises S256-only PKCE instead of the bridge's
         * hardcoded ['plain', 'S256']. See FIX 4 / EnforcePkceS256.
         */
        'discovery' => false,
        /**
         * When set to true, this package will expose the JSON Web Key Set endpoint.
         */
        'jwks' => true,
        /**
         * Optional URL to change the JWKS path to align with your custom Passport routes.
         * Defaults to /oauth/jwks
         */
        'jwks_url' => '/oauth/jwks',
        /**
         * The bridge's userinfo route is disabled here and re-registered in
         * routes/oidc.php so it can carry a per-IP throttle (throttle:oidc-ip)
         * in addition to Passport's auth:api guard. See FIX 5.
         */
        'userinfo' => false,
    ],

    /**
     * Settings for the discovery endpoint
     */
    'discovery' => [
        /**
         * Hide scopes that aren't from the OpenID Core spec from the Discovery,
         * default = false (all scopes are listed)
         */
        'hide_scopes' => false,
    ],

    /**
     * The signer to be used
     */
    'signer' => Sha256::class,

    /**
     * Optional associative array that will be used to set headers on the JWT
     */
    'token_headers' => [],

    /**
     * By default, microseconds are included.
     */
    'use_microseconds' => true,

    /**
     * Value for the issuedBy params. By default: laravel to get the scheme and host from the $_SERVER variable.
     * Options: laravel (use Request to extract scheme and host), server (use $_SERVER to detect)
     * or another string that will be used as-is
     */
    'issuedBy' => 'laravel',

    /**
     * By default, https is enforced. Disabled only for local http dev.
     */
    'forceHttps' => env('OPENID_FORCE_HTTPS', true),
];
