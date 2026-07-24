<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force PKCE S256 on the OAuth authorization endpoint. league/oauth2-server
 * falls back to the weaker 'plain' transform when code_challenge_method is
 * omitted, so a present code_challenge whose method is not exactly S256 is
 * rejected with 400 — the 'plain' downgrade never reaches the grant. Missing
 * PKCE entirely is left to Passport (public clients still require it, per
 * AC-AUTH-5). Registered on all Passport routes via config('passport.middleware');
 * only acts on the authorize route.
 */
class EnforcePkceS256
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->routeIs('passport.authorizations.authorize')
            && $request->filled('code_challenge')
            && $request->query('code_challenge_method') !== 'S256'
        ) {
            abort(400, 'code_challenge_method must be S256; plain PKCE is not supported.');
        }

        return $next($request);
    }
}
