<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Bare (no web/session group), mirroring the OIDC bridge's own routes.
        then: function (): void {
            require __DIR__.'/../routes/oidc.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        // Shared preference cookies (theme + language) are scoped to the parent
        // domain so they cross every ecosystem tool. Each tool has its own APP_KEY,
        // so they must stay UNencrypted to be readable across tools.
        $middleware->encryptCookies(except: ['nexo-lang', 'nexo-theme']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Machine endpoints answer JSON (a 401, not an HTML login redirect) when
        // unauthenticated. /oauth/userinfo is token-guarded (auth:api); the
        // browser-facing /oauth/authorize is intentionally excluded so it keeps
        // redirecting unauthenticated users to login (AC-AUTH-2).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('oauth/userinfo'),
        );
    })->create();
