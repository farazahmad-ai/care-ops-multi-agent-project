# ADR-007: Frontend Authentication via Keycloak OIDC Authorization Code Flow with PKCE

- **Status:** Accepted
- **Date:** 2026-11-14
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Section 9 `FR-1`, `FR-2`, Section 10 `NFR-1`, `NFR-2`), [`docs/architecture.md`](../architecture.md) (Section 4, 7), [`docs/roadmap.md`](../roadmap.md) (Tasks 0.4, 1.3, 1.7, 1.9; Open Item `O8`), [ADR-003](ADR-003-permissions-outside-the-model.md)

## Context

The CareOps platform frontend is a modern React Single Page Application (SPA) with TypeScript and Tailwind CSS. Users belong to three distinct business roles (`FR-1`):
1. `staff` (e.g. Care Coordinators, HR Officers, Finance Officers) — can submit requests and view live status (`Screen 1`).
2. `team_lead` (e.g. Operations Team Leads, Recruitment Leads) — can access the approval/escalation queue (`Screen 2`) for low-confidence or routine reviews.
3. `department_head` (e.g. HR Director, Finance Head, Operations Director) — can approve high-risk actions (job offers, payment mutations) and view the leadership dashboard (`Screen 3`).

Open Item `O8` in [`docs/roadmap.md`](../roadmap.md) requires deciding the frontend authentication flow. Because a React SPA runs entirely in the user's web browser, it is a **public client**: it cannot securely store a static client secret.

## Decision

Adopt the **OpenID Connect (OIDC) / OAuth 2.0 Authorization Code Flow with PKCE (Proof Key for Code Exchange, RFC 7636)** for frontend authentication with **Keycloak** as the Identity Provider (IdP).

### How It Works in Practice

1. **User Initiation:** When a staff member, team lead, or department head navigates to the CareOps web app (`https://careops.company.com` or `http://localhost:3000`), the React application checks if an active session exists in memory.
2. **Dynamic Secret Handshake (PKCE):**
   - The React app generates a cryptographically random string called the **Code Verifier**.
   - It hashes this string using SHA-256 to create the **Code Challenge**.
   - The browser redirects the user to the Keycloak login portal, passing the `client_id`, `redirect_uri`, and the `code_challenge` (never the raw verifier).
3. **Authentication at Keycloak:**
   - The user authenticates directly at Keycloak (email, password, and optional MFA).
   - Keycloak checks credentials and finds the user's realm roles (`staff`, `team_lead`, `department_head`).
4. **Authorization Code Return:**
   - Keycloak redirects the browser back to the React app callback URL with a short-lived, one-time **Authorization Code**.
5. **Token Exchange:**
   - The React app sends an HTTP `POST` to Keycloak's token endpoint containing the authorization code and the original raw **Code Verifier**.
   - Keycloak verifies that `SHA256(code_verifier) == code_challenge`. This proves that the exact same browser session that started the login is completing it, preventing authorization code interception attacks.
   - Keycloak issues a cryptographically signed JSON Web Token (JWT) **Access Token** and an **ID Token**.
6. **API Request Authorization:**
   - The React app stores the Access Token in browser memory (never in vulnerable `localStorage`).
   - Every API call to `services/agent-api` includes the header:
     `Authorization: Bearer <access_token>`
   - The FastAPI backend validates the JWT signature against Keycloak's public keys (`JWKS`), extracts the `sub` (user ID) and roles, and forwards them to OPA for policy evaluation (`User ∩ Agent`, [ADR-003](ADR-003-permissions-outside-the-model.md)).

## Consequences

### Positive
- **Highest Security for SPAs:** Complies with current IETF OAuth 2.0 Security Best Current Practice. Zero static secrets stored in the browser JavaScript bundle.
- **Role-Based Access Control (RBAC):** Roles (`staff`, `team_lead`, `department_head`) are embedded directly in the JWT payload, allowing FastAPI and OPA to authorize requests without querying Keycloak on every API call.
- **Enterprise Ready:** Keycloak can later federate with enterprise identity systems (Google Workspace, Microsoft Entra ID / Azure AD, Okta) without altering platform code.
- **Seamless User Experience:** Standard web redirect flow familiar to all corporate users.

### Negative / Trade-offs
- Requires running and maintaining a Keycloak container (`quay.io/keycloak/keycloak`) in Docker Compose with imported realm settings.
- Token refresh must be handled in memory or through secure HTTP-only session cookies.

## Alternatives Considered

- **OAuth 2.0 Implicit Flow:** Historically used for SPAs, but now officially deprecated by IETF because tokens are returned directly in the URL hash, vulnerable to leakage and history snooping.
- **Resource Owner Password Credentials (ROPC):** React app collects username and password directly. Severely discouraged because it exposes user credentials to frontend code and prevents multi-factor authentication (MFA).
- **Custom Username/Password with API Sessions:** Reinventing authentication in FastAPI increases maintenance overhead, lacks centralized SSO, and complicates auditability (`NFR-11`).
