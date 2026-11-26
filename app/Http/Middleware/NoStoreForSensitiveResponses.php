<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The identity provider's own `no-store` paths, on top of the shared
 * NexoNoStore (templates/nexo-ui: every signed-in response and the auth
 * screens, identical in the 6 apps). What only Nexo ID has: the OAuth/OIDC
 * browser surface (authorize, the end_session confirmation) and the sign-out
 * pages, which a guest reaches too (ADR-010, contract §3). Without it the back
 * button after signing out could bring a confirmation or a consent screen back
 * from the browser cache.
 *
 * The public, cacheable OIDC documents (discovery and the JWKS) stay
 * cacheable: they hold no personal data and every client caches them.
 */
class NoStoreForSensitiveResponses
{
    /** @var array<int, string> */
    private const SENSITIVE_PATHS = [
        'logout',
        'logout/*',
        'signed-out',
        'profile',
        'profile/*',
        'email/*',
        'oauth/*',
    ];

    /** @var array<int, string> */
    private const CACHEABLE_PATHS = [
        'oauth/jwks',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is(...self::CACHEABLE_PATHS) && $request->is(...self::SENSITIVE_PATHS)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
