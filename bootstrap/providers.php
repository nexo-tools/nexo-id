<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use OpenIDConnect\Laravel\PassportServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PassportServiceProvider::class,
];
