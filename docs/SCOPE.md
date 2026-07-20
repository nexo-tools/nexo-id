# SCOPE — Nexo ID

<!-- Living record: every new idea lands here (docs: commit) BEFORE being implemented. -->

## Value proposition

Nexo ID is the central identity service (SSO) of the Nexo ecosystem: **one account, every Nexo tool**. Each product stays separate in data and logic, connected only by identity. Like its siblings (Nexo Links, Nexo Agenda), it is open source and multi-instance: anyone can use Alvaro's hosted instance or self-host their own.

Concrete benefits:

- Users register once and access all tools (less friction, more cross-tool adoption).
- Nexo Short gets mandatory registration as an anti-abuse barrier for free from day one (first client).
- New tools (Nexo Events) are born integrated instead of building auth again.
- Foundation for the ecosystem narrative and a central "your tools" account page.

## Current ecosystem (context, not scope)

| Tool | Status | Auth today | Domain |
|---|---|---|---|
| Nexo Links | Production | Own users table (Laravel) | nexolinks.alvarocdev.com |
| Nexo Agenda | Production | Own users table (Laravel) | nexoagenda.alvarocdev.com |
| Nexo Short | Not built; domain bought | — (will require accounts, anti-abuse) | nxo.li |
| Nexo Events | Not built | — | TBD |

Note: Nexo Short lives on **its own domain (nxo.li)**, outside alvarocdev.com — a hard constraint on the SSO mechanism (see ADR-003).

## MVP

### In

**Standalone identity service:**
- Registration, login, logout, password recovery, basic profile (display name, email).
- Email verification at signup (blocking for SSO use — anti-abuse requirement for Nexo Short).
- Server-side sessions with hashed random tokens; session listing/revocation on the profile page.

**SSO provider (per ADR-003):**
- Standard OAuth 2.0 authorization code + PKCE flow with identity layer (id token / userinfo), so clients on any domain, stack, or host can integrate.
- Client (tool) registration and management.
- Silent SSO: an active Nexo ID session signs the user into any tool without re-entering credentials.
- Central logout invalidates the Nexo ID session.

**Security (MVP requirements, not extras — public surface = attack surface):**
- Password hashing with bcrypt/argon2id (framework-native), never homemade.
- Rate limiting on login, registration, and recovery — per account and per IP.
- Cookies `Secure` + `HttpOnly` + `SameSite=Lax`; HTTPS everywhere.
- Recovery tokens: single-use, short expiry, stored hashed.
- Email notification on password change.
- Security headers + strict CSP (sync-test pattern from the sibling tools).
- Dependency audit in CI.

**Ecosystem integration:**
- Integration guide + reference client configuration for Laravel tools (and generic OIDC for any stack).
- SSO integration of Nexo Links and Nexo Agenda with account linking by verified email (per ADR-005 — no user data to migrate: both registries are empty as of Gate 0).

**Nexo conventions (inherited from siblings):**
- Open source (MIT), self-hostable, English docs.
- Zero external requests at runtime (no CDNs/fonts/trackers); SMTP only for transactional mail.
- i18n en/es/pt from day one.
- Instance-configurable attribution footer (`NEXO_ATTRIBUTION_*` env pattern).
- Backups verified + uptime monitoring once in production.

### Out (with the why)

- **Social login (Google/GitHub)** — not needed for migration parity; adds external dependencies against the zero-external-requests principle for the core flow. Post-v1 candidate.
- **2FA/TOTP** — high value for an identity service, but not required to reach parity with today's tools and unblock Nexo Short. First hardening candidate after migration (Phase 5).
- **"Your tools" central account page** — the visible face of the ecosystem, but not needed for SSO to work. Planned as Phase 5, not MVP.
- **Organizations/teams, roles across tools** — each tool owns its authorization; Nexo ID only does identity/authentication.
- **Admin panel beyond basics** — client management can start config/CLI-based; UI later.
- **Magic links / passwordless** — siblings' guest flows stay their own; Nexo ID is account-based.

## Product principles

- **Identity only.** Nexo ID authenticates; each tool authorizes. No tool-specific data ever lives in Nexo ID.
- **Standard protocol over clever tricks.** Any client on any domain/stack/host must be able to integrate; self-hosters must not be locked in (see ADR-003/ADR-004).
- **Tools degrade gracefully.** A tool must keep serving already-authenticated sessions if Nexo ID is unreachable.
- **Multi-instance by design.** Nothing hardcoded to Alvaro's instance; everything instance-specific is env-configurable.
- **Security is the product.** This service guards every account of the ecosystem; security minimums are acceptance criteria, not backlog.

## Backlog post-v1

- 2FA (TOTP + backup codes) — after migration; highest-value hardening. (Better Auth module in starter-master is prior art for scope.)
- Social login — only if adoption data justifies it.
- "Your tools" account page with per-tool connection status.
- Email change flow with re-verification of both addresses.
- Admin UI for client/instance management.
- Audit log of account events (logins, password changes) visible to the user.
- Translated es/pt framework messages (`validation.*` / `auth.*`) — English-only in Phase 1 (UI strings are translated; framework messages are laravel-lang territory). Low effort, deferred to keep Phase 1 focused.
