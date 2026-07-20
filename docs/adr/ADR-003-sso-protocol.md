# ADR-003 — SSO protocol: standard OAuth 2.0 authorization code + PKCE (OIDC-style), not a parent-domain cookie

- **Date:** 2026-07-19
- **Status:** Accepted (Gate 0 sign-off, 2026-07-19)

## Context

The evaluation document proposed SSO via a session cookie on `.alvarocdev.com`: every subdomain reads the cookie and validates it server-side. That mechanism only works if **all clients share the parent domain**. Checking that assumption against reality:

- **Nexo Short — the first client — lives on `nxo.li`**, not under alvarocdev.com. The parent-domain cookie cannot sign it in. The flagship use case breaks on day one.
- Future TS tools may deploy on Vercel or other hosts/domains.
- **Self-hosted instances** are on arbitrary third-party domains by definition.
- A parent-domain cookie is readable by *every* subdomain of alvarocdev.com — any future subdomain (or a compromised one) can exfiltrate ecosystem sessions.
- Cross-tool session validation via shared DB access couples every tool to Nexo ID's schema; via a bespoke endpoint, it invents a protocol that third-party stacks can't reuse.

## Decision

1. Nexo ID implements **OAuth 2.0 authorization code flow with PKCE**, plus an identity layer (ID token / `userinfo` endpoint) following OpenID Connect conventions. Whether the MVP is fully OIDC-certified-compliant or "OAuth 2.0 + OIDC-shaped identity endpoints" is resolved by the **Phase 1 spike** against the chosen Laravel library (Laravel Passport as first candidate) — the wire format tools depend on must be standard either way.
2. **Nexo ID's session cookie lives only on its own host** (host-only cookie on `nexoid.alvarocdev.com`), with `Secure` + `HttpOnly` + `SameSite=Lax`. No parent-domain cookie.
3. **Silent SSO** works for any client on any domain: a tool redirects to Nexo ID; an active session there returns an authorization code without user interaction. This is what makes "one login, every tool" real across nxo.li, subdomains, and self-hosted domains alike.
4. Each tool validates and keeps **its own local session** after the SSO handshake (standard practice), which also satisfies the graceful-degradation principle: Nexo ID being down blocks new logins, never active sessions.
5. Central logout invalidates the Nexo ID session (new SSO attempts require credentials). Propagating logout to tools' local sessions (back-channel logout) is post-v1; documented as a known limitation.

## Alternatives considered

- **Parent-domain cookie on `.alvarocdev.com`** — rejected: fails for nxo.li (first client!), Vercel-hosted tools, and every self-hosted instance; widens the attack surface to all subdomains; not reusable by third parties. Also unnecessary as a UX optimization: silent SSO via redirect achieves the same effect.
- **Hybrid (formal protocol + parent-domain cookie for own subdomains)** — rejected: two code paths for one behavior; the cookie adds only a saved redirect while keeping its security downside.
- **SAML** — rejected: enterprise-weight, poor fit for this ecosystem's size and stacks.
- **Custom token-verification endpoint (bespoke protocol)** — rejected: reinvents OAuth without its review; every stack would need a custom client instead of off-the-shelf OIDC libraries (Laravel Socialite provider, Better Auth generic OIDC — both standard).

## Consequences

- Any stack integrates with off-the-shelf libraries: Laravel tools via a Socialite/OIDC provider, future TS tools via Better Auth's generic OAuth/OIDC support, third parties with whatever their framework offers.
- Strategic consequence: there is **no architectural reason to keep tools on alvarocdev.com subdomains** for SSO — domain choices become pure branding/ops decisions.
- More upfront work than a cookie (client registration, token endpoints, keys), mitigated by using a maintained server library — validated in the Phase 1 spike, including Hostinger constraints (`proc_open`/`exec` disabled).
- Supersedes sections 2–3 of the evaluation document (nexo-id.md); its section 5 security minimums remain valid and live in SCOPE.
