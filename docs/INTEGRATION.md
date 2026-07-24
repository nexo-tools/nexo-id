# Integrating a tool with Nexo ID (OIDC)

Nexo ID is a standard **OAuth 2.0 + PKCE / OpenID Connect** provider. Any client on any domain, stack, or host integrates with off-the-shelf OIDC/OAuth libraries — no Nexo-specific SDK. This guide is for developers wiring a Nexo tool (or a third-party app) to a Nexo ID instance.

## 1. Discover the endpoints

Every instance publishes an OIDC discovery document:

```
GET https://<nexoid-host>/.well-known/openid-configuration
```

It returns `issuer`, `authorization_endpoint`, `token_endpoint`, `userinfo_endpoint`, and `jwks_uri`. Read these at runtime rather than hard-coding paths — a self-hosted instance may differ.

## 2. Register your client

On the Nexo ID instance, an operator registers your client (first-party, public/PKCE — no secret):

```bash
php artisan nexo:sso-client "My Tool" --redirect=https://mytool.example/auth/callback
# prints the client_id (a uuid). Repeat --redirect for multiple URIs.
```

Redirect URIs are matched **exactly** on authorization — register every callback you use.

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

The `id_token` is an RS256 JWT with `iss`, `aud` (your client_id), `sub` (the user's stable uuid), and `exp`. Verify its signature against the provider's JWKS (`jwks_uri`) before trusting it. Example (Laravel, lcobucci/jwt):

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

## Framework shortcuts

- **Laravel tools** — use a generic OIDC/OAuth2 Socialite provider (e.g. `socialiteproviders`-style generic driver) pointed at the discovery document; it handles steps 3–6.
- **TypeScript tools (starter-master lineage)** — Better Auth's generic OAuth/OIDC provider consumes the same discovery document.

## Notes & limits (MVP)

- **Account linking:** match your local user to the Nexo ID account by the verified `email` on first sign-in, then store `sub` (see ADR-005). Never create duplicate local accounts for the same verified email.
- **Graceful degradation:** keep your own local session after the handshake. If Nexo ID is unreachable, already-signed-in users keep working; only new logins are blocked (ADR-004).
- **Logout:** central logout ends the Nexo ID session (new logins need re-auth). Tokens you already hold stay valid until they expire — back-channel logout to clients is a later phase.
- **Self-hosting:** Nexo ID is optional. A tool ships standalone local auth and treats Nexo ID as an env-configured OIDC provider (`NEXO_SSO_*`), so it can point at any OIDC provider or none (ADR-004). The reusable Laravel client pattern lands in Phase 3 (Nexo Short).
