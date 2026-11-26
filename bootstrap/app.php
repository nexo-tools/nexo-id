<?php

use App\Http\ExpiredSignOut;
use App\Http\Middleware\EnsureIdpSessionActive;
use App\Http\Middleware\NexoNoStore;
use App\Http\Middleware\NoStoreForSensitiveResponses;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\OperatorAlerts;
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
            // no-store on everything with a session and on the auth screens
            // (templates/nexo-ui, identical in the 6 apps), plus the IdP's own
            // extras: the OAuth surface and the sign-out pages.
            NexoNoStore::class,
            NoStoreForSensitiveResponses::class,
            // Last, so a browser whose sid was ended elsewhere is signed out
            // before the route runs — and still gets the headers above.
            EnsureIdpSessionActive::class,
        ]);

        // Shared preference cookies (theme + language) are scoped to the parent
        // domain so they cross every ecosystem tool. Each tool has its own APP_KEY,
        // so they must stay UNencrypted to be readable across tools.
        $middleware->encryptCookies(except: ['nexo-lang', 'nexo-theme']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Something broke and nobody is watching: this ecosystem has no error
        // tracker by design (a third party observing users contradicts the
        // product), so the operator hears about it by mail — deduped by
        // exception identity, and a queued job only once it gives up. The
        // queue half is wired in ->booted() below. See templates/nexo-ops.
        $exceptions->report(fn (Throwable $e) => OperatorAlerts::report($e));

        // «Cerrar sesión» with an expired page (CONTRATO-F3 §0.1, v2.1 item 2):
        // never a 419 and never a sign-out by itself — a redirect to the
        // «¿Cerrar sesión en todo Nexo?» confirmation (or the signed-out page).
        // See App\Http\ExpiredSignOut for why it redirects instead of rendering.
        $exceptions->render(fn (Throwable $e, Request $request) => ExpiredSignOut::render($e, $request));

        // Machine endpoints answer JSON (a 401, not an HTML login redirect) when
        // unauthenticated. /oauth/userinfo is token-guarded (auth:api); the
        // browser-facing /oauth/authorize is intentionally excluded so it keeps
        // redirecting unauthenticated users to login (AC-AUTH-2).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('oauth/userinfo'),
        );
    })
    ->booted(fn (Application $app) => OperatorAlerts::listen($app['events']))
    ->create();
