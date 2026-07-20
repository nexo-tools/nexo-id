# ADR-002 — Stack and hosting: Laravel + MySQL on the existing Hostinger shared hosting

- **Date:** 2026-07-19
- **Status:** Accepted (Gate 0 sign-off, 2026-07-19)

## Context

The evaluation document assumed PHP + MySQL on the current shared hosting, but was written before the standards system existed. The standards declare a strategic direction of **TypeScript end-to-end** (starter-master: NestJS/Next, PostgreSQL, Better Auth), while keeping **Laravel + MySQL as the pragmatic stack** for what deploys on Hostinger shared (PHP-only, prepaid ~3 more years). There is no VPS yet ("sin apuro"). Facts that weigh here:

- Both production clients (Nexo Links, Nexo Agenda) are Laravel on this same hosting; the deploy playbook (`deploy-laravel-hostinger`) is verified in production twice.
- starter-master's auth module (Better Auth + 2FA) is real prior art, but it cannot run on shared PHP hosting; running a TS identity provider would require new infrastructure (VPS or Vercel + external Postgres), splitting operations across hosts for the most availability-critical piece of the ecosystem.
- The identity provider is the piece every tool depends on; it should live on the most boring, proven infrastructure available today.

## Decision

1. **Laravel (latest, currently 13.x) + MySQL, deployed on the existing Hostinger shared hosting** — same stack, host, and playbook as the tools it serves. $0 new cost.
2. The **SSO protocol is stack-agnostic by requirement** (ADR-003): choosing PHP for the server must not constrain clients (TS tools on Vercel, third-party self-hosts) nor block a future rewrite/migration of the server itself.
3. Revisit this decision **when the VPS exists** or when Nexo ID's load outgrows shared hosting — a standard protocol boundary makes the server swappable without touching clients.

## Alternatives considered

- **TypeScript (starter-master + Better Auth) on Vercel/VPS** — aligned with the strategic direction and reuses the built auth module, but requires infrastructure that doesn't exist yet, adds new moving parts (Postgres hosting, a second ops surface) for the ecosystem's single point of failure, and delays the first client (Nexo Short). Rejected *for now*; the protocol boundary keeps this door open.
- **PHP without framework (as sketched in the evaluation doc)** — rejected: the sibling tools prove Laravel on this host; hand-rolling auth primitives without framework support increases security risk for zero benefit.

## Consequences

- Deploy, CSP/.htaccess, SMTP, and cron patterns are already solved and documented (skill + sibling repos).
- The Hostinger PHP environment must be validated against the chosen OAuth/OIDC server library early — this is the Phase 1 spike (planning-by-stages rule: first task may be a spike).
- Diverges from the strategic TS direction knowingly; recorded here so the future migration ADR has its context.
