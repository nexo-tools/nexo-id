<?php

namespace App\Http\Middleware;

use App\Models\OauthClient;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the OAuth authorization endpoint on a verified email (AC-AUTH-3): a
 * logged-in but unverified user cannot obtain an authorization code and is sent
 * to the verification notice. Applied to all Passport routes via
 * config('passport.middleware'), but only acts on the authorize route.
 *
 * prompt=none exception (silent SSO, AC-PROMPT-NONE-3): a silent attempt must
 * never render UI, so instead of the verification notice the client gets an
 * OIDC `interaction_required` error redirect — but only to a redirect_uri
 * registered EXACTLY for the given client (no open redirect). The code is
 * still never issued. Guests and every other prompt keep Passport's own
 * handling (it answers `login_required` to a session-less prompt=none itself).
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
            if ($this->wantsSilent($request) && ($errorRedirect = $this->silentErrorRedirect($request)) !== null) {
                return $errorRedirect;
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }

    /** prompt is a space-delimited list; `none` overrides the rest (as Passport reads it). */
    private function wantsSilent(Request $request): bool
    {
        return in_array('none', explode(' ', (string) $request->query('prompt', '')), true);
    }

    /**
     * The OIDC error redirect for an unusable-without-interaction session, or
     * null when the client/redirect_uri pair cannot be validated — then the
     * interactive behavior stands and nothing is ever redirected blindly.
     */
    private function silentErrorRedirect(Request $request): ?RedirectResponse
    {
        $clientId = (string) $request->query('client_id', '');
        $redirectUri = (string) $request->query('redirect_uri', '');

        if ($clientId === '' || $redirectUri === '') {
            return null;
        }

        $client = OauthClient::query()->find($clientId);

        if ($client === null || $client->revoked || ! in_array($redirectUri, $client->redirect_uris, true)) {
            return null;
        }

        $query = http_build_query(array_filter([
            'error' => 'interaction_required',
            'error_description' => 'The session cannot be used without user interaction.',
            'state' => (string) $request->query('state', ''),
        ], fn (string $value): bool => $value !== ''));

        return redirect()->away($redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').$query);
    }
}
