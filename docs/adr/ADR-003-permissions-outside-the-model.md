# ADR-003: Permissions are enforced outside the model (Keycloak, OPA and MCP), never in prompts

- **Status:** Accepted
- **Date:** 2026-11-10 (updated 2026-11-12 to align with [`docs/project-brief.md`](../project-brief.md) v0.2)
- **Deciders:** Project owner
- **Related:** [`docs/project-brief.md`](../project-brief.md) (Sections 2, 5.6, 8.1, `FR-1`, `FR-2`, `FR-11`, `FR-18`, `FR-21`, `NFR-1` to `NFR-4`), [`docs/architecture.md`](../architecture.md) (Section 8), [`AGENTS.md`](../../AGENTS.md) (Hard Boundaries #2, #3, #4)

## Context

Agents read untrusted external text (candidate CVs, client emails, policy documents, tool outputs) and invoke tools that touch regulated personal data and company money. Prompt injection means a model can be tricked into ignoring its instructions. A system prompt saying *"do not access payroll"* is therefore **a request, not a security control**.

Furthermore, as documented in [`docs/project-brief.md`](../project-brief.md) (Section 2) and [ADR-004](ADR-004-simulated-existing-company.md), the pre-existing CareOps legacy API (`company-sandbox/`) authenticates all callers with a single shared bearer `SERVICE_TOKEN`. On its own, the legacy API cannot distinguish a recruiter, a finance head, or an AI agent. Fine-grained access control and accountability must therefore be enforced upstream before any call reaches `company-sandbox/`.

## Decision

1. **Identity (`FR-1`):** Human users sign in through **Keycloak (OIDC)** and receive a system role (`staff`, `team_lead`, `department_head`, plus domain-specific approval authority per `Q-6` / Task 4.4, such as Recruitment Lead or HR Director for job offers). Each AI helper (`supervisor`, `recruitment`, `hr`, `ops`, `finance`, `auditor`) has its own distinct service identity and a **named human owner** (Section 12 & 18.3 of [`docs/project-brief.md`](../project-brief.md)). Agents act **on behalf of a user**, never with a shared human login.
2. **Policy as Code (`FR-2`):** Authorization decisions are evaluated by **OPA (Rego)** as the strict intersection of user permissions and agent permissions (`User Permissions ∩ Agent Permissions`). Example: the `recruitment` agent may read candidate and compliance records, but is denied payroll or invoice tools even if invoked by a Finance Head; conversely, the `finance` agent is denied payroll mutations if invoked by a user without finance permissions.
3. **Separation of Duties (`FR-15`):** The `auditor` (Checker) agent has a read-only review identity and is blocked by OPA policy from reviewing any action where it was the actor.
4. **Enforcement Point (`NFR-1`, `NFR-2`):** All access to business systems goes exclusively through **MCP servers** (`services/mcp-servers`). Every MCP tool call validates its input schema, queries OPA, enforces idempotency keys on state changes (`FR-19`), and writes an immutable audit log record (`FR-18`, including denials) to PostgreSQL. **Only the MCP servers hold `SERVICE_TOKEN`**; agents have zero database credentials (`:3307` is unreachable) and zero legacy API tokens.
5. **Hard Human Approval Gates (`FR-11`, `NFR-4`):** High-risk actions (**job offers / hiring, payments, payroll changes, contract changes, deleting personal data**) and low-confidence outputs (`FR-10`) cannot execute in the MCP layer without a recorded approval token issued by a human holding the required role in `Screen 2` (Approval & Escalation Queue).
6. **Defence in Depth (`FR-20`, `FR-21`, `NFR-13`):** Input screening for prompt injection, PII redaction before external model calls (or routing sensitive data to the local model via LiteLLM), Pydantic structured output validation, and per-run step/cost/timeout caps (`£0.50` per-run cap, `NFR-10`) wrap every run as additional safety layers.
7. **UI is Not Security:** `Screen 3` (Heads-Only Dashboard, `FR-23`) is enforced by FastAPI and OPA. Hiding UI links is cosmetic only.

## Consequences

**Positive**
- A successful prompt injection inside a CV or document is strictly contained to the intersection of the user's and agent's least-privilege read scope (`NFR-3`).
- Policies are versioned, reviewable and unit-tested with `opa test`.
- Every tool call (allowed or denied) produces an immutable audit trail supporting UK GDPR accountability (`NFR-11`).

**Negative**
- Additional infrastructure (Keycloak, OPA) and a small latency overhead per tool call (bounded within `NFR-7`).
- Every new MCP tool requires a matching Rego policy and policy unit test before CI passes (`NFR-2`).

## Testing requirements

- OPA policy unit tests (`opa test`) for every role (`staff`, `team_lead`, `department_head`), agent identity, and high-risk approval rule.
- An adversarial prompt-injection test suite in `evals/` (`FR-24`, Task 7.1: malicious CVs, poisoned SOPs, poisoned tool outputs) verifying zero unauthorized tool executions (`NFR-3`).
- A CI gate (Task 6.5) that fails the build if any MCP tool lacks an input schema, an OPA policy, an audit log call, or a test.

## Alternatives considered

- **Prompt-based restrictions:** not a security control against prompt injection.
- **Role checks hard-coded inside agent Python code:** scattered, hard to audit, and drifts over time.
- **Giving agents `SERVICE_TOKEN` or direct MySQL credentials:** unacceptable blast radius and violates UK GDPR least-privilege principles.
