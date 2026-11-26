<?php

namespace App\Http\Middleware;

use App\Auth\IdpSessions;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A browser whose IdP session (sid) was ended elsewhere stops being signed in
 * here on its next request (ADR-010).
 *
 * With the database session driver the revocations also delete the session
 * row, so this is the second lock; with any other driver (a self-host on file
 * or redis sessions) it is the only one. It also covers the sid named by an
 * id_token_hint at end_session, which can belong to a different browser than
 * the one that sent it.
 *
 * Runs on the web group, which on protected routes is after `auth` (framework
 * middleware priority), so the route is answered here — except authorize,
 * which continues as a guest. Sessions without a sid (opened before session control,
 * or guests) pass untouched: they get one lazily on their next authorize.
 */
class EnsureIdpSessionActive
{
    public function __construct(private IdpSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $sid = $this->sessions->current($request->session());

        if ($sid === null || ! $this->sessions->isEnded($sid)) {
            return $next($request);
        }

        // Current device only: whatever ended the sid already rotated the
        // remember token where the contract says so. Cycling it again here
        // would kill the «remember me» cookie that «sign out the other
        // sessions» just re-issued to the device that asked for it.
        $guard = Auth::guard('web');
        $guard instanceof SessionGuard ? $guard->logoutCurrentDevice() : $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // /oauth/authorize answers guests itself, and must: with prompt=none it
        // owes the tool an `error=login_required` redirect, never a login page
        // (a tool's 15-minute re-validation is exactly that request). Without
        // prompt it sends the guest to sign in and back, as for anyone else.
        if ($request->routeIs('passport.authorizations.authorize')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // guest() remembers this URL (GET) so signing back in lands here.
        return redirect()->guest(route('login'));
    }
}
