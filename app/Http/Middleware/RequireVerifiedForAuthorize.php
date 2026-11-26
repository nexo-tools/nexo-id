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
 * to the verification notice. Appended to the `web` group (bootstrap/app.php)
 * and only acts on the authorize route. It must run AFTER the session starts:
 * from config('passport.middleware') — where it lived until 2026-11-26 — it
 * ran before the web group, read no user at all and let unverified accounts
 * get codes (only the tests' actingAs() made it look enforced;
 * AuthorizationTest pins the order now).
 *
 * prompt=none exception (silent SSO, AC-PROMPT-NONE-3): a silent attempt must
 * never render UI, so instead of the verification notice the client gets an
 * OIDC `interaction_required` error redirect — but only to a redirect_uri
 * registered EXACTLY for the given client (no open redirect). The code is
 * still never issued. Guests and every other prompt keep Passport's own
 * handling (it answers `login_required` to a session-less prompt=none itself).
 *
 * The interactive answer keeps the authorize request as the intended URL, the
 * way Laravel's guest redirect does for the login: verifying the email
 * (VerifyEmailController → redirect()->intended()) then resumes it and the
 * tool gets its code, instead of leaving the person on /profile. It is always
 * this server's own /oauth/authorize (the request URL), and Passport validates
 * it again when it resumes, so it can never point anywhere else. An intended
 * authorize request of the SAME client is left alone: it is the one the person
 * signed up through (prompt=create), whose flow the tool keeps for an hour,
 * while a later plain sign-in flow of that tool lasts ten minutes.
 *
 * Every parameter is read as a plain string only: since this gate runs for
 * real, `?prompt[]=none` or `?client_id[]=…` from an unverified session must
 * not become a 500.
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

            if (! $this->intendsSameClient($request)) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('verification.notice');
        }

        return $next($request);
    }

    /** prompt is a space-delimited list; `none` overrides the rest (as Passport reads it). */
    private function wantsSilent(Request $request): bool
    {
        return in_array('none', explode(' ', $this->param($request, 'prompt')), true);
    }

    /**
     * The OIDC error redirect for an unusable-without-interaction session, or
     * null when the client/redirect_uri pair cannot be validated — then the
     * interactive behavior stands and nothing is ever redirected blindly.
     */
    private function silentErrorRedirect(Request $request): ?RedirectResponse
    {
        $clientId = $this->param($request, 'client_id');
        $redirectUri = $this->param($request, 'redirect_uri');

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
            'state' => $this->param($request, 'state'),
        ], fn (string $value): bool => $value !== ''));

        return redirect()->away($redirectUri.(str_contains($redirectUri, '?') ? '&' : '?').$query);
    }

    /** Whether url.intended is already an authorize request of this request's client. */
    private function intendsSameClient(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');
        $clientId = $this->param($request, 'client_id');

        if (! is_string($intended) || $clientId === '') {
            return false;
        }

        $target = parse_url($intended);

        if (! is_array($target) || ($target['path'] ?? '') !== parse_url($request->url(), PHP_URL_PATH)) {
            return false;
        }

        parse_str((string) ($target['query'] ?? ''), $query);

        return ($query['client_id'] ?? null) === $clientId;
    }

    /** A query parameter as a string, or '' when it is missing or not a plain string. */
    private function param(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
