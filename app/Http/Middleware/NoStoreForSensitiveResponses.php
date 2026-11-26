<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Cache-Control: no-store, private` on every response that a browser must not
 * keep: anything answered to a signed-in session, the account entry points
 * (login, register, password recovery, verification) and the OAuth/OIDC
 * surface (ADR-010, contract §3). Without it the back button after signing out
 * shows the account page again from the browser cache.
 *
 * The public, cacheable OIDC documents (discovery and the JWKS) stay
 * cacheable: they hold no personal data and every client caches them.
 */
class NoStoreForSensitiveResponses
{
    /** @var array<int, string> */
    private const SENSITIVE_PATHS = [
        'login',
        'logout',
        'logout/*',
        'register',
        'forgot-password',
        'reset-password/*',
        'verify-email',
        'verify-email/*',
        'email/*',
        'profile',
        'profile/*',
        'oauth/*',
    ];

    /** @var array<int, string> */
    private const CACHEABLE_PATHS = [
        'oauth/jwks',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isSensitive($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function isSensitive(Request $request): bool
    {
        if ($request->is(...self::CACHEABLE_PATHS)) {
            return false;
        }

        if ($request->is(...self::SENSITIVE_PATHS)) {
            return true;
        }

        // Only consult the session guard when a session is running (the web
        // group): resolving it on a bare route would try the remember cookie.
        return $request->hasSession() && $request->user('web') !== null;
    }
}
