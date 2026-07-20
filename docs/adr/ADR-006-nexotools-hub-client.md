# ADR-006 — NexoTools hub as a future first-party OIDC client ("your tools" boundary)

- **Date:** 2026-07-19
- **Status:** Proposed (drafted from the NexoTools Phase 0 planning; needs owner sign-off — Gate 0 closed before this was registered)

## Context

NexoTools (`~/nexotools`) is the ecosystem hub: a page where non-technical users see every Nexo tool and jump into them. Its v1 is deliberately static with **zero dependency on Nexo ID**. Its v2 turns the hub into the visible face of the ecosystem account: login with Nexo ID, "your tools" with signed-in direct access, and cross-tool discovery. This dependency must be registered on both sides (NexoTools ADR-003 is the mirror of this one). There is also a potential overlap: this project's Phase 5 backlog includes a "your tools" central account page.

## Decision

1. **NexoTools v2 is a future first-party client** of Nexo ID, consuming the standard OAuth 2.0 + PKCE / OIDC contract (ADR-003) like any tool — no bespoke endpoints. It becomes a registered client when its v2 phase opens.
2. **Precondition:** NexoTools v2 waits for Phase 2 (provider live) and reuses the Phase 3 client pattern. Nexo ID plans owe it nothing earlier; NexoTools v1 works with Nexo ID absent.
3. **"Your tools" boundary:** Nexo ID owns **account management** (profile, sessions, security, connected tools as account data); the hub owns **discovery and access** (what tools exist, which ones you use, jumping into them). If the overlap is still ambiguous when either side opens that phase, it is resolved jointly at that point — neither project implements the other's half unilaterally.

## Consequences

- NexoTools v2 appears as a use case for the SSO provider; nothing in the MVP scope changes.
- If v2 needs data the standard contract doesn't expose (e.g. "which tools has this user activated"), that becomes a joint ADR on both sides at v2 planning time — not an assumed feature.
