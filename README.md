<div align="center">

<img src="resources/brand/isotype.svg" width="88" alt="Nexo ID isotype">

# Nexo ID

**One account for every Nexo tool — the open-source identity provider (OAuth 2.0 / OIDC single sign-on) of the Nexo ecosystem.**
Self-hostable, standards-based, no external requests.

[![CI](https://github.com/nexo-tools/nexo-id/actions/workflows/ci.yml/badge.svg)](https://github.com/nexo-tools/nexo-id/actions/workflows/ci.yml)
![License: MIT](https://img.shields.io/badge/License-MIT-7C3AED.svg)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777bb4.svg)
![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20.svg)

[Live instance](https://nexoid.alvarocdev.com) ·
[Integration guide](docs/INTEGRATION.md) ·
[Deployment guide](DEPLOYMENT.md) ·
[Scope](docs/SCOPE.md)

</div>

---

Nexo ID is the **central identity service (SSO) of the Nexo ecosystem**: one account,
every Nexo tool. It's a self-hostable, standards-based **OAuth 2.0 + OpenID Connect
provider**, so each product stays separate in data and logic, connected only by
identity. Like its siblings, it is open source and multi-instance — use the hosted
instance or run your own. It is **live in production at
[nexoid.alvarocdev.com](https://nexoid.alvarocdev.com)**.

## Why Nexo ID?

- **One account, every tool** — users register once and sign into any Nexo tool. New
  tools are born integrated instead of building authentication all over again.
- **Standards-based OIDC** — OAuth 2.0 authorization-code flow with **PKCE (S256
  enforced)** and a full OpenID Connect identity layer: `id_token`, `userinfo`,
  discovery (`/.well-known/openid-configuration`) and JWKS. Any client, on any domain,
  stack or host, integrates with off-the-shelf OIDC libraries.
- **Silent SSO + central logout** — an active Nexo ID session signs the user into any
  tool without re-entering credentials; RP-initiated logout ends the session and
  redirects only to validated URIs (never an open redirect).
- **Secure by default** — email verification (blocking for SSO use), per-account and
  per-IP rate limiting on login / registration / recovery, single-use hashed recovery
  tokens, password-change email notifications, `Secure` + `HttpOnly` + `SameSite`
  cookies, strict CSP + security headers, and a dependency audit in CI.
- **Session control** — users list and revoke their active sessions (individually or
  all others) from their profile.
- **Self-hostable** — run your own identity provider for your own ecosystem, or point
  your tools at the hosted instance.
- **Private by design** — **zero external requests** at runtime (no CDNs, no font
  services, no trackers); SMTP only for transactional mail.
- **Multilingual** — English, Spanish and Portuguese (`en`/`es`/`pt`) with a
  translatable `/help` center.

## Tech stack

PHP 8.3+ · Laravel 13 · Blade + Alpine.js + Tailwind CSS (Vite) · MySQL

OIDC built on [Laravel Passport](https://laravel.com/docs/passport) +
[laravel-openid-connect](https://github.com/jeremy379/laravel-openid-connect).
Quality: [Pest](https://pestphp.com) · [Pint](https://laravel.com/docs/pint) ·
[Larastan](https://github.com/larastan/larastan) · GitHub Actions CI.
Zero external runtime requests — system font stack, no CDNs.

## Quick start (local)

Requirements: Docker — everything else runs in containers via
[Laravel Sail](https://laravel.com/docs/sail).

```bash
git clone https://github.com/nexo-tools/nexo-id.git
cd nexo-id
cp .env.example .env
docker run --rm -v "$(pwd):/app" -w /app composer:latest composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Open [http://localhost](http://localhost). Local email inbox (Mailpit):
[http://localhost:8025](http://localhost:8025). To generate the OAuth signing keys and
register your first client application, follow the
**[integration guide](docs/INTEGRATION.md)**.

## Self-hosting

Nexo ID is a standard Laravel app — run your own identity provider on your own domain.
See **[DEPLOYMENT.md](DEPLOYMENT.md)** to deploy, and the
**[integration guide](docs/INTEGRATION.md)** to connect tools. Key configuration
(see `.env.example`):

| Env var | Purpose | Default |
| --- | --- | --- |
| `NEXO_ATTRIBUTION_LABEL` | "Powered by" label in the shared footer | `made with Nexo ID` |
| `NEXO_ATTRIBUTION_URL` | Footer link target | `https://alvarocdev.com` |
| `NEXO_SUPPORT_EMAIL` | Contact address on the `/help` center | unset |
| `NEXO_SUPPORT_URL` | Support URL (wins over the mailto when set) | unset |
| `NEXO_PASSWORD_MIN_LENGTH` | Minimum account password length | `8` |

## Status

**Phases 1 & 2 complete — live at [nexoid.alvarocdev.com](https://nexoid.alvarocdev.com).**
The SSO provider is running: OAuth 2.0 + PKCE / OIDC (authorize, token, `id_token`,
userinfo, discovery, JWKS), silent SSO, central logout, plus accounts, email
verification and session management. Phase 3 (a reusable OIDC client pattern, Nexo
Short as the first consumer, and ops hardening) is nearly complete: the client
template ships and Nexo Short runs SSO-only against this provider in production.

## Documentation

- [Scope](docs/SCOPE.md) · [Architecture](docs/ARCHITECTURE.md) · [Integration](docs/INTEGRATION.md)
- Specs: [core](SPEC.md) · [SSO provider](SPEC-sso.md) · [reusable client](SPEC-client.md)
- [Plan & gates](docs/PLAN.md) · [Decisions (ADRs)](docs/adr/)
- [Deployment guide](DEPLOYMENT.md)

## Nexo ecosystem

Nexo is a family of open-source, self-hostable tools that share one visual identity
([nexo-brand](https://github.com/nexo-tools)), one optional account
([Nexo ID](https://github.com/nexo-tools/nexo-id) SSO) and one set of engineering
standards. Every tool runs **fully standalone** — the ecosystem is opt-in.

| Tool | What it is | Repo |
| --- | --- | --- |
| **Nexo Tools** | Ecosystem hub — discover the tools and hop between them with one account | [nexo-tools](https://github.com/nexo-tools/nexo-tools) |
| **Nexo Links** | Link-in-bio you host yourself (Linktree alternative) | [nexo-links](https://github.com/nexo-tools/nexo-links) |
| **Nexo Agenda** | Bookings for service businesses (AgendaPro / Fresha / Booksy alternative) | [nexo-agenda](https://github.com/nexo-tools/nexo-agenda) |
| **Nexo Short** | Self-hosted URL shortener | [nexo-short](https://github.com/nexo-tools/nexo-short) |
| **Nexo Events** | Event tickets and passes | [nexo-events](https://github.com/nexo-tools/nexo-events) |
| **Nexo ID** | One account for every tool — OAuth 2.0 / OIDC SSO | — you are here |

New to Nexo? Start at **[nexotools.alvarocdev.com](https://nexotools.alvarocdev.com)**.
Built by **[alvarocdev.com](https://alvarocdev.com)** — the tech behind Nexo.

## License

MIT License © [Alvaro Carrizales](https://alvarocdev.com) — the tech behind Nexo.
