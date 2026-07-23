# Nexo ID

One account, every Nexo tool — the open source identity service (SSO) of the Nexo ecosystem.

Status: Phases 1 & 2 complete — the SSO provider (OAuth 2.0 + PKCE / OIDC: authorize, token, id_token, userinfo, discovery, JWKS, silent SSO, central logout) is **live at https://nexoid.alvarocdev.com**. Phase 3 (reusable OIDC client pattern + Nexo Short as the first consumer + ops hardening) is nearly complete: the client template ships and Nexo Short runs SSO-only against this provider in production.

- Scope: [docs/SCOPE.md](docs/SCOPE.md)
- Specs: [SPEC.md](SPEC.md) (core) · [SPEC-sso.md](SPEC-sso.md) (SSO provider) · [SPEC-client.md](SPEC-client.md) (reusable client pattern)
- Plan & gates: [docs/PLAN.md](docs/PLAN.md)
- Architecture: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- Tool integration: [docs/INTEGRATION.md](docs/INTEGRATION.md)
- Decisions: [docs/adr/](docs/adr/)
