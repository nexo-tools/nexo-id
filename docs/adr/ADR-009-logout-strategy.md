# ADR-009 — Logout strategy: RP-initiated now, back-channel deferred

- **Date:** 2026-07-24
- **Status:** Accepted (ecosystem hardening M0; evaluation triggered by ">1 SSO client live/integrated")

## Context

nexoid is the ecosystem IdP. When the audit ran, only one client (nexoshort) was live; now nexolinks and nexoagenda are integrated and nexoevents/nexotools are scaffolded as clients. With more than one relying party, the **logout blast radius** matters: today, logging out of one tool (a) does not end the nexoid session, so the next tool logs the user straight back in silently (silent SSO), and (b) does not end sessions at the *other* tools. For a shared-account ecosystem this is a real UX and security gap — a user who logs out on a shared device reasonably expects to be logged out everywhere.

OIDC defines three logout mechanisms:
1. **RP-Initiated Logout** — the client sends the user to the IdP's `end_session_endpoint` (with `id_token_hint` + `post_logout_redirect_uri`); the IdP ends *its* session and redirects back. Simple; no client-side endpoints.
2. **Front-Channel Logout** — the IdP renders hidden iframes to each client's logout URL. Fragile (third-party-cookie/iframe blocking).
3. **Back-Channel Logout** — the IdP POSTs a signed `logout_token` to each client's back-channel endpoint server-to-server; each client must track sessions by `sid`/`sub` and invalidate them. Robust but the heaviest: requires session-index tracking and a logout endpoint in every client template.

## Decision

1. **Adopt RP-Initiated Logout as the ecosystem standard.** nexoid exposes an `end_session_endpoint` (advertised in discovery); the shared `nexo-sso-client` template's logout, when SSO is active, redirects through it with `id_token_hint` + `post_logout_redirect_uri` (validated against the client's registered post-logout URIs). This makes "log out" end the central session, so silent SSO no longer resurrects it. This is the first-class, low-cost win and covers the common case.
2. **Defer Back-Channel Logout** until the ecosystem has multiple *independently-hosted* live clients with real cross-tool sessions (trigger: ≥3 live SSO clients, or the first report of a stale sibling session). It is the only mechanism that also kills the *other* clients' sessions, but it needs per-client session tracking and a logout endpoint fanned into the template — a cost not justified while standalone is the default and the live client count is low.
3. **Do not use Front-Channel Logout** — third-party-cookie erosion makes the iframe approach unreliable; skip straight to back-channel when the trigger fires.

## Scheduling

- **RP-initiated logout is implemented in the M4 rollout, not M0.** It is a cross-cutting change — one endpoint in nexoid plus a template change that must re-propagate to every client — so it lands when the brand/standards rollout is already touching every tool (`inbox/brand-unification/MASTER-PLAN.md`, fase M4), with the nexoid side landing in M4's nexoid sub-phase. M0's deliverable is this decision.
- Back-channel logout stays in BACKLOG with the trigger above.

## Consequences

- Until M4 ships RP-initiated logout, the standing guidance for shared devices is unchanged: use the nexoid session page to end the central session. No regression — this ADR only schedules an improvement.
- The `nexo-sso-client` template gains a documented logout contract (redirect to `end_session_endpoint` when `NEXO_SSO_ENABLED`, local logout otherwise) — captured in `SPEC-client.md` when M4 implements it.
- Back-channel readiness is a known future cost; the template's session handling should avoid decisions that would make `sid`-based invalidation hard to add later.
