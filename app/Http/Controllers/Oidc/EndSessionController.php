<?php

namespace App\Http\Controllers\Oidc;

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
 * OIDC RP-initiated (front-channel) logout — the end_session_endpoint. A tool
 * sends the browser here to end the central Nexo ID session (with the local one
 * it already ended). Advertised in discovery because the route is named
 * `openid.end_session_endpoint` (DiscoveryController checks Route::has).
 *
 * Anti open-redirect (ADR-009): a `post_logout_redirect_uri` is honoured ONLY
 * when it is a registered redirect URI of the client named in a signature-valid
 * `id_token_hint`. Anything else lands on our own "signed out" page — we never
 * bounce the browser to an arbitrary URL.
 */
class EndSessionController
{
    public function __construct(private ClientRepository $clients) {}

    public function __invoke(Request $request): RedirectResponse|View
    {
        $idTokenHint = (string) $request->query('id_token_hint', '');
        $postLogoutRedirectUri = (string) $request->query('post_logout_redirect_uri', '');
        $state = (string) $request->query('state', '');

        // End the browser (Nexo ID) session. Idempotent: logging out a guest is a
        // harmless no-op, so an unauthenticated hit is fine.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($postLogoutRedirectUri !== '' && $this->isRegisteredForHint($idTokenHint, $postLogoutRedirectUri)) {
            $target = $postLogoutRedirectUri;
            if ($state !== '') {
                $target .= (str_contains($target, '?') ? '&' : '?').http_build_query(['state' => $state]);
            }

            return redirect()->away($target);
        }

        // No hint, unknown client, or an unregistered URI: our own page, never a
        // redirect to a caller-supplied destination.
        return view('auth.logged-out');
    }

    /**
     * True only when $uri is exactly a registered redirect URI of the client
     * named in a genuine id_token_hint. The hint's signature is verified against
     * our own signing key (a forged hint can't point `aud` at a real client);
     * expiry is intentionally NOT checked — logout hints are routinely expired.
     */
    private function isRegisteredForHint(string $idTokenHint, string $uri): bool
    {
        if ($idTokenHint === '') {
            return false;
        }

        try {
            $config = Configuration::forSymmetricSigner(
                new Sha256,
                InMemory::file(storage_path('oauth-public.key')),
            );
            $token = $config->parser()->parse($idTokenHint);

            if (! $token instanceof UnencryptedToken) {
                return false;
            }

            if (! $config->validator()->validate($token, new SignedWith($config->signer(), $config->signingKey()))) {
                return false; // forged or tampered hint
            }

            $audiences = (array) $token->claims()->get('aud', []);
        } catch (Throwable) {
            return false; // malformed hint
        }

        foreach ($audiences as $clientId) {
            $client = $this->clients->find((string) $clientId);
            // redirect_uris is a Passport accessor (Attribute) casting to array.
            $registered = $client !== null ? (array) $client->getAttribute('redirect_uris') : [];
            if (in_array($uri, $registered, true)) {
                return true;
            }
        }

        return false;
    }
}
