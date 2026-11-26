<?php

namespace App\Http\Controllers\Oidc;

use App\Auth\ConnectedTools;
use App\Auth\IdpSessions;
use App\Models\OauthClient;
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
 *
 * Without a hint a tool still says who it is (CONTRATO-F3 §0.1, v2.1 item 2:
 * its logout with an expired session, which has no id_token left to send):
 * `client_id` + `post_logout_redirect_uri`. The URI is honoured only when it is
 * exactly a registered redirect URI of THAT client (OIDC RP-Initiated Logout
 * §2: client_id is how the OP attributes the URI without a hint) — after the
 * confirmation with a session, at once without one. With a hint as well, the
 * client_id must be one of the hint's audiences.
 */
class EndSessionController
{
    public function __construct(
        private ClientRepository $clients,
        private IdpSessions $sessions,
        private ConnectedTools $tools,
    ) {}

    public function __invoke(Request $request): RedirectResponse|View
    {
        $postLogoutRedirectUri = $this->param($request, 'post_logout_redirect_uri');
        $state = $this->param($request, 'state');
        $clientId = $this->param($request, 'client_id');
        $hint = $this->parseHint($this->param($request, 'id_token_hint'));
        $user = Auth::guard('web')->user();

        $hintIsValid = $hint !== null && ($user === null || (string) $user->getAuthIdentifier() === $hint['sub']);

        if (! $hintIsValid) {
            // No hint to vouch for the request: the client_id alone may name
            // where to go, never what to end.
            $return = $this->registeredReturn([$clientId], $postLogoutRedirectUri);

            if ($user !== null) {
                return view('auth.logout-confirm', [
                    'return' => $return !== null ? ['client_id' => $clientId, 'post_logout_redirect_uri' => $postLogoutRedirectUri, 'state' => $state] : null,
                    'keepUrl' => $this->keepSignedInUrl($clientId),
                ]);
            }

            return $return !== null ? redirect()->away($this->withState($return, $state)) : view('auth.logged-out');
        }

        $this->endBrowserSession($request, also: $hint['sid']);

        $audiences = $clientId === '' ? $hint['aud'] : (in_array($clientId, $hint['aud'], true) ? [$clientId] : []);

        if (($return = $this->registeredReturn($audiences, $postLogoutRedirectUri)) !== null) {
            return redirect()->away($this->withState($return, $state));
        }

        // Unknown client or an unregistered URI: our own page, never a redirect
        // to a caller-supplied destination.
        return view('auth.logged-out');
    }

    /**
     * Where «Mantener la sesión abierta» goes on the confirmation. A tool that
     * sent the person here has already ended its own session, so when the
     * client_id is a tool of the ecosystem registry (non-revoked, matched by
     * origin as in «Herramientas conectadas») it is that tool's url + entry:
     * its panel, signed in again through Nexo ID with zero clicks. Anything
     * else — no client_id, an unknown or revoked one, a third-party client
     * whose front door Nexo ID does not know — the account.
     */
    private function keepSignedInUrl(string $clientId): string
    {
        $client = $clientId !== '' ? OauthClient::query()->find($clientId) : null;

        if ($client !== null && ! $client->revoked) {
            $described = $this->tools->describe($client);

            if ($described['key'] !== null && $described['openUrl'] !== null) {
                return $described['openUrl'];
            }
        }

        return route('profile.show');
    }

    /**
     * The confirmed sign-out (POST, CSRF-protected) from «¿Cerrar sesión en
     * todo Nexo?». Ends this browser's session, then goes back to the tool
     * that asked when its client_id + post_logout_redirect_uri pair is
     * registered (checked again here: the form is not trusted), or to our own
     * signed-out page.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $this->endBrowserSession($request);

        $return = $this->registeredReturn(
            [$this->param($request, 'client_id')],
            $this->param($request, 'post_logout_redirect_uri'),
        );

        return $return !== null
            ? redirect()->away($this->withState($return, $this->param($request, 'state')))
            : redirect()->route('signed-out');
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
     * $uri when it is exactly a registered redirect URI of one of $audiences
     * (non-revoked clients only), else null. Never a caller-supplied
     * destination that no client registered.
     *
     * @param  array<int, string>  $audiences
     */
    private function registeredReturn(array $audiences, string $uri): ?string
    {
        if ($uri === '') {
            return null;
        }

        foreach ($audiences as $clientId) {
            if ($clientId === '') {
                continue;
            }

            $client = $this->clients->find($clientId);
            // redirect_uris is a Passport accessor (Attribute) casting to array.
            $registered = $client !== null && ! $client->getAttribute('revoked') ? (array) $client->getAttribute('redirect_uris') : [];
            if (in_array($uri, $registered, true)) {
                return $uri;
            }
        }

        return null;
    }

    /**
     * A query or form parameter as a string, or '' when it is missing or not a
     * plain string (`?client_id[]=x` must not become a 500).
     */
    private function param(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    private function withState(string $target, string $state): string
    {
        return $state === '' ? $target : $target.(str_contains($target, '?') ? '&' : '?').http_build_query(['state' => $state]);
    }
}
