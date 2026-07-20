<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the OAuth authorization endpoint on a verified email (AC-AUTH-3): a
 * logged-in but unverified user cannot obtain an authorization code and is sent
 * to the verification notice. Applied to all Passport routes via
 * config('passport.middleware'), but only acts on the authorize route.
 */
class RequireVerifiedForAuthorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $request->routeIs('passport.authorizations.authorize')
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
