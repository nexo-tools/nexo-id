<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `prompt=create` on /oauth/authorize (OpenID Connect «Initiating User
 * Registration» 1.0): a tool's «Crear cuenta» sends the visitor here, and they
 * must land on the sign-up screen — and, once their email is verified, back in
 * the tool with a code (CONTRATO-F3 v2.2, «alta que vuelve a la tool»).
 *
 * It only rewrites Passport's own answer, never decides on its own: when the
 * request comes from a guest and Passport says «sign in first» (the guest
 * redirect to the login, which it only gives AFTER validating the client, the
 * redirect_uri and the rest of the request), the redirect goes to /register
 * instead, with the authorize URL WITHOUT `create` as the intended URL, so that
 * coming back does not ask for a second sign-up. Registration → verification →
 * redirect()->intended() → this same authorize, now with a session → the code.
 *
 *  - An invalid client_id or redirect_uri: Passport answers with its error, no
 *    login redirect, so there is nothing to rewrite — no sign-up screen naming
 *    a tool and no redirect anywhere.
 *  - With a session: untouched, exactly as if `create` were not there
 *    (the person already has an account).
 *  - `none` together with `create`: `none` wins (Passport answers a guest with
 *    `login_required` to the tool, not with a login redirect).
 *  - The verified-email gate stays where it was: the code is still only issued
 *    by Passport behind RequireVerifiedForAuthorize.
 *
 * Appended to the `web` group after EnsureIdpSessionActive (bootstrap/app.php),
 * so it sees the real signed-in user and its url.intended is written before
 * the session is saved; only acts on the authorize route.
 */
class SignUpOnPromptCreate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('passport.authorizations.authorize') || ! $this->asksToSignUp($request) || $request->user() !== null) {
            return $next($request);
        }

        $response = $next($request);

        if (! $response instanceof RedirectResponse || $response->getTargetUrl() !== route('login')) {
            return $response;
        }

        $request->session()->put('url.intended', $this->withoutCreate($request));

        return redirect()->route('register');
    }

    /** `create` in the space-delimited prompt, and no `none` (which wins, as Passport reads it). */
    private function asksToSignUp(Request $request): bool
    {
        $prompt = $this->promptValues($request);

        return in_array('create', $prompt, true) && ! in_array('none', $prompt, true);
    }

    /**
     * The same authorize request with `create` taken out of prompt (and prompt
     * dropped when nothing else is left).
     */
    private function withoutCreate(Request $request): string
    {
        $query = $request->query();
        $prompt = array_values(array_filter($this->promptValues($request), fn (string $value): bool => $value !== 'create'));

        if ($prompt === []) {
            unset($query['prompt']);
        } else {
            $query['prompt'] = implode(' ', $prompt);
        }

        return $query === [] ? $request->url() : $request->url().'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<int, string> */
    private function promptValues(Request $request): array
    {
        $prompt = $request->query('prompt');

        return is_string($prompt) ? array_values(array_filter(explode(' ', $prompt), fn (string $value): bool => $value !== '')) : [];
    }
}
