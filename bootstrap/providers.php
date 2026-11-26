<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\OidcServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    // Extends the OIDC bridge's provider (which extends Passport's) with the
    // IdP session sid — ADR-010. Neither package is auto-discovered.
    OidcServiceProvider::class,
];
