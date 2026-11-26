<?php

namespace App\Http\Controllers\Oidc;

use App\Auth\IdpSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Throwable;

/**
 * OIDC RP-initiated logout — the end_session_endpoint. A tool sends the browser
 * here (GET, no CSRF possible) after ending its own session. Advertised in
 * discovery because the route is named `openid.end_session_endpoint`.
 *
 * Since session control (ADR-010) ending a Nexo ID session also signs the
 * device out of every tool, so a bare GET can no longer be trusted to mean
 * "the person asked for this" (S9: a link in a mail would sign them out of the
 * whole ecosystem). The endpoint acts at once only with a valid
 * `id_token_hint` — signature-valid, and for the account signed in here (if
 * any); expiry is ignored, logout hints are routinely expired. Then it ends
 * the hint's sid and this browser's sid, and honours `post_logout_redirect_uri`
 * only when it is exactly a registered redirect URI of the hint's client.
 *
 * Without a valid hint and with a session, it asks first: «¿Cerrar sesión en
 * todo Nexo?», a POST with CSRF (confirm()). Without a session there is
 * nothing to end: the signed-out page. Never an open redirect.
 */
class EndSessionController
{
    public function __construct(
        private ClientRepository $clients,
        private IdpSessions $sessions,
    ) {}

    public function __invoke(Request $request): RedirectResponse|View
    {
        $postLogoutRedirectUri = (string) $request->query('post_logout_redirect_uri', '');
        $state = (string) $request->query('state', '');
        $hint = $this->parseHint((string) $request->query('id_token_hint', ''));
        $user = Auth::guard('web')->user();

        $hintIsValid = $hint !== null && ($user === null || (string) $user->getAuthIdentifier() === $hint['sub']);

        if (! $hintIsValid) {
            return $user !== null ? view('auth.logout-confirm') : view('auth.logged-out');
        }

        $this->endBrowserSession($request, also: $hint['sid']);

        if ($postLogoutRedirectUri !== '' && $this->isRegistered($hint['aud'], $postLogoutRedirectUri)) {
            $target = $postLogoutRedirectUri;
            if ($state !== '') {
                $target .= (str_contains($target, '?') ? '&' : '?').http_build_query(['state' => $state]);
            }

            return redirect()->away($target);
        }

        // Unknown client or an unregistered URI: our own page, never a redirect
        // to a caller-supplied destination.
        return view('auth.logged-out');
    }

    /**
     * The confirmed sign-out (POST, CSRF-protected) from «¿Cerrar sesión en
     * todo Nexo?». Ends this browser's session; no redirect, since nothing
     * here vouches for a destination.
     */
    public function confirm(Request $request): View
    {
        $this->endBrowserSession($request);

        return view('auth.logged-out');
    }

    /**
     * End this browser's IdP session (and $also, the hint's sid when it names
     * another one), then the Nexo ID session itself. Idempotent for guests.
     */
    private function endBrowserSession(Request $request, ?string $also = null): void
    {
        $this->sessions->end([$this->sessions->current($request->session()), $also], 'end_session');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * The claims of a genuine id_token_hint — its signature verified against
     * our own signing key, so a forged hint cannot point `aud` at a real
     * client — or null when there is no usable hint.
     *
     * @return array{sub: string, sid: string|null, aud: array<int, string>}|null
     */
    private function parseHint(string $idTokenHint): ?array
    {
        if ($idTokenHint === '') {
            return null;
        }

        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256,
                InMemory::file(storage_path('oauth-public.key')),
            );
            $token = $config->parser()->parse($idTokenHint);

            if (! $token instanceof UnencryptedToken) {
                return null;
            }

            if (! $config->validator()->validate($token, new SignedWith($config->signer(), $config->signingKey()))) {
                return null; // forged or tampered hint
            }

            $claims = $token->claims();
            $sub = (string) $claims->get('sub', '');
            $sid = $claims->get('sid');
        } catch (Throwable) {
            return null; // malformed hint
        }

        if ($sub === '') {
            return null;
        }

        return [
            'sub' => $sub,
            'sid' => is_string($sid) && $sid !== '' ? $sid : null,
            'aud' => array_map('strval', (array) $claims->get('aud', [])),
        ];
    }

    /**
     * True only when $uri is exactly a registered redirect URI of one of the
     * hint's audiences.
     *
     * @param  array<int, string>  $audiences
     */
    private function isRegistered(array $audiences, string $uri): bool
    {
        foreach ($audiences as $clientId) {
            $client = $this->clients->find($clientId);
            // redirect_uris is a Passport accessor (Attribute) casting to array.
            $registered = $client !== null ? (array) $client->getAttribute('redirect_uris') : [];
            if (in_array($uri, $registered, true)) {
                return true;
            }
        }

        return false;
    }
}
