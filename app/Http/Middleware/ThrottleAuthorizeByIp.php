<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-IP ceiling on the OAuth authorization endpoint (flood / enumeration abuse
 * protection). Passport owns the authorize route registration, so this rides
 * config('passport.middleware') and acts only on that route — login POST and
 * /oauth/userinfo get their per-IP ceilings via named throttle limiters. Runs
 * before the request is validated so malformed floods are counted too.
 *
 * The ceiling is NEXO_OIDC_AUTHORIZE_PER_MINUTE (default 60): the tools
 * re-validate every 15 minutes with prompt=none, so a shared wifi multiplies
 * authorize calls per IP and the operator must be able to raise it without a
 * code change.
 */
class ThrottleAuthorizeByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('passport.authorizations.authorize')) {
            $key = 'oauth-authorize|'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, max(1, (int) config('nexo.authorize_rate.per_minute', 60)))) {
                abort(Response::HTTP_TOO_MANY_REQUESTS, 'Too many authorization requests.');
            }

            RateLimiter::hit($key); // default 60s decay window
        }

        return $next($request);
    }
}
