<?php

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
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

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
        // a 419 there is a person who wants out and an old CSRF token, not an
        // attack — a forged cross-site POST is exactly what this must not
        // honour, so it never signs anybody out by itself. Signed in (the
        // «remember me» cookie still holds): the same «¿Cerrar sesión en todo
        // Nexo?» confirmation end_session asks, a fresh CSRF-protected POST.
        // Not signed in any more: there is nothing to end — the signed-out page.
        $exceptions->render(function (Throwable $e, Request $request) {
            $mismatch = $e instanceof TokenMismatchException || $e->getPrevious() instanceof TokenMismatchException;

            if (! $mismatch || ! $request->isMethod('POST') || ! $request->routeIs('logout', 'logout.everywhere')) {
                return null;
            }

            return Auth::guard('web')->check()
                ? response()->view('auth.logout-confirm', [], 200)
                : redirect()->route('signed-out');
        });

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
