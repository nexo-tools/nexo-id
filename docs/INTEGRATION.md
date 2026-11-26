# Integrating a tool with Nexo ID (OIDC)

Nexo ID is a standard **OAuth 2.0 + PKCE / OpenID Connect** provider. Any client on any domain, stack, or host integrates with off-the-shelf OIDC/OAuth libraries — no Nexo-specific SDK. This guide is for developers wiring a Nexo tool (or a third-party app) to a Nexo ID instance.

## 1. Discover the endpoints

Every instance publishes an OIDC discovery document:

```
GET https://<nexoid-host>/.well-known/openid-configuration
```

It returns `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, `jwks_uri` and `end_session_endpoint`, plus `backchannel_logout_supported` / `backchannel_logout_session_supported` when the instance sends back-channel logouts (see §7). Read these at runtime rather than hard-coding paths — a self-hosted instance may differ.

## 2. Register your client

On the Nexo ID instance, an operator registers your client (first-party, public/PKCE — no secret):

```bash
php artisan nexo:sso-client "My Tool" --redirect=https://mytool.example/auth/callback \
    --backchannel=https://mytool.example/auth/nexo/backchannel-logout
# prints the client_id (a uuid). Repeat --redirect for multiple URIs; --backchannel is optional.

# Later, on an existing client (idempotent, prints the final state):
php artisan nexo:sso-client:update <client_id> \
    --backchannel=https://mytool.example/auth/nexo/backchannel-logout \
    --add-redirect=https://mytool.example/auth/nexo/signed-out
```

Redirect URIs are matched **exactly** on authorization — register every callback you use, and also the page you
want the browser sent to after logout (`post_logout_redirect_uri` is only honoured if it is one of them).

## 3. Authorization request (code + PKCE)

Generate a PKCE `code_verifier` (43–128 chars) and its `code_challenge` = base64url(sha256(verifier)). Redirect the user to:

```
GET {authorization_endpoint}?
    client_id={client_id}
    &redirect_uri=https://mytool.example/auth/callback
    &response_type=code
    &scope=openid profile email
    &state={random_state}
    &code_challenge={challenge}
    &code_challenge_method=S256
```

- First-party clients are **consent-free**: if the user has an active, verified Nexo ID session they are redirected straight back with a `code` (silent SSO). Otherwise they log in (or verify their email) first, then return.
- Non-first-party clients are shown a **consent screen** listing the requested scopes before any `code` is issued (the screen exists today; broader third-party onboarding/polish is a later phase).
- Always send and check `state` (CSRF protection).

The callback receives `?code=…&state=…`.

## 4. Token exchange

Exchange the code (server-side) for tokens:

```
POST {token_endpoint}
    grant_type=authorization_code
    client_id={client_id}
    redirect_uri=https://mytool.example/auth/callback
    code_verifier={verifier}
    code={code}
```

Response: `access_token`, `id_token` (JWT), `refresh_token`, `token_type=Bearer`, `expires_in`.

## 5. Verify the id_token

The `id_token` is an RS256 JWT with `iss`, `aud` (your client_id), `sub` (the user's stable uuid), `exp`, the `nonce` you sent to `/authorize`, and `sid` — the Nexo ID session it was issued in (keep it: back-channel logouts name it). Verify its signature against the provider's JWKS (`jwks_uri`) before trusting it. Example (Laravel, lcobucci/jwt):

```php
$config = Configuration::forSymmetricSigner(
    new Rsa\Sha256(),
    InMemory::plainText($publicKeyFromJwks), // or the provider's oauth-public.key
);
$token = $config->parser()->parse($idToken);
$config->validator()->assert($token, new SignedWith($config->signer(), $config->signingKey()));
```

Two gotchas that trip up external consumers:

- **JWKS returns a JWK, not a PEM.** `jwks_uri` serves a key as JSON (`kty: RSA`, base64url `n`/`e` — modulus and exponent), *not* a PEM certificate. You must reconstruct the public key from `n`/`e` before verifying (most OIDC libraries do this for you; hand-rolled verifiers must convert JWK → PEM/RSA key, e.g. via `phpseclib` or a JWK library). The `InMemory::plainText($publicKeyFromJwks)` above assumes you already converted it (or fetched the provider's `oauth-public.key` PEM directly).
- **id_tokens are signed without a `kid` header.** The bridge issues a single-key JWKS and omits the `kid` from both the JWT header and the JWK. Clients that key their verification on `kid` matching must tolerate its absence — fall back to the sole key in the set rather than requiring a `kid` match (firebase/php-jwt, for one, otherwise throws).

## 6. userinfo

Fetch claims with the access token:

```
GET {userinfo_endpoint}
Authorization: Bearer {access_token}
```

Returns `sub` and, per granted scope: `email` + `email_verified` (scope `email`), `name` (scope `profile`). `sub` is the account's uuid — use it as your foreign key to the Nexo ID account.

## 7. Logout (ADR-010)

Nexo ID owns the session of the ecosystem: one sign-out signs the device out of Nexo ID **and** of every tool that
signed in during that Nexo ID session.

**RP-initiated (your «Sign out»).** End your local session, then send the browser to:

```
GET {end_session_endpoint}?id_token_hint={the id_token you kept}
    &post_logout_redirect_uri=https://mytool.example/auth/nexo/signed-out
    &state={optional}
```

With a valid hint (our signature; expiry is ignored) Nexo ID ends that session right away and redirects to
`post_logout_redirect_uri` if it is one of your registered redirect URIs — otherwise it shows its own "signed out"
page. **Without a valid hint** it asks the person first («¿Cerrar sesión en todo Nexo?»): a bare link must not be
able to sign someone out of everything. A tool whose own session already expired has no id_token left to send, so it
says who it is instead — `client_id={your client_id}` plus `post_logout_redirect_uri` (and `state`). Nexo ID honours
that URI only when it is one of **that** client's registered redirect URIs: after the confirmation when the browser
still has a Nexo ID session, at once when it has none. Any other URI, or none, ends on Nexo ID's own "signed out"
page. With a hint and a `client_id`, the `client_id` must be one of the hint's audiences. Your CSP must allow the issuer in `form-action`, since
your logout form's redirect goes there.

**Back-channel (Nexo ID tells you).** Register a `backchannel_logout_uri` (§2). When a Nexo ID session in which
your client got a code ends — sign-out anywhere, «Cerrar esta sesión», «Cerrar las demás sesiones», «Cerrar sesión
en todos los dispositivos», a password change or reset, an account deletion — Nexo ID sends:

```
POST {backchannel_logout_uri}
Content-Type: application/x-www-form-urlencoded

logout_token=<JWT>
```

The `logout_token` ([OIDC Back-Channel Logout 1.0](https://openid.net/specs/openid-connect-backchannel-1_0.html)):
RS256 with the same key and header set as the id_token, header `typ: logout+jwt`; claims `iss` (same as the
id_token), `aud` (your client_id), `iat`, `exp` (iat + 120 s), `jti` (uuid), `sub`, `sid` (absent only on
account deletion, which means "every session of this `sub`"), and
`events: {"http://schemas.openid.net/event/backchannel-logout": {}}`. It never carries `nonce`.

Validate it like the id_token (JWKS, `alg` RS256, `iss`, `aud`), plus: `iat` within a few minutes, `events` has the
back-channel key, no `nonce`, `sid` or `sub` present, `jti` not seen before. Then revoke the matching local
sessions and answer `200` with an empty body (`400 {"error":"invalid_request"}` if the token is invalid). Answer
from the registered URI itself: Nexo ID does not follow redirects, so a 3xx counts as a failed delivery. Nexo ID
waits at most 2 s (all tools in parallel) and retries a failed delivery — no connection, timeout, 3xx, 5xx, 408,
429 — at 1, 5, 15 and 60 min and then hourly for 24 h; any other 4xx is not retried. The Laravel tools get all of this
from the `nexo-sso-client` template.

## Framework shortcuts

- **Laravel tools** — use a generic OIDC/OAuth2 Socialite provider (e.g. `socialiteproviders`-style generic driver) pointed at the discovery document; it handles steps 3–6.
- **TypeScript tools (starter-master lineage)** — Better Auth's generic OAuth/OIDC provider consumes the same discovery document.

## Notes & limits (MVP)

- **Account linking:** match your local user to the Nexo ID account by the verified `email` on first sign-in, then store `sub` (see ADR-005). Never create duplicate local accounts for the same verified email.
- **Graceful degradation:** keep your own local session after the handshake. If Nexo ID is unreachable, already-signed-in users keep working; only new logins are blocked (ADR-004).
- **Logout:** see §7. Access tokens you already hold stay valid until they expire (60 min); your own session is what the back-channel ends.
- **Self-hosting:** Nexo ID is optional. A tool ships standalone local auth and treats Nexo ID as an env-configured OIDC provider (`NEXO_SSO_*`), so it can point at any OIDC provider or none (ADR-004). The reusable Laravel client pattern lands in Phase 3 (Nexo Short).
