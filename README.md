# CareOps Agent Platform

A multi-agent platform for a (fictional) UK care-staffing business (**CareOps Ltd**). Deterministic rules, plain Python workflows, and bounded AI agents handle routine recruitment, HR, operations and finance admin on top of a pre-existing legacy company system (`company-sandbox/`), with permissions enforced outside the model, humans approving risky actions, and every action audited and costed.

> CareOps Ltd is fictional. All data in this repo is 100% synthetic.

**Start here:**
- [`docs/project-brief.md`](docs/project-brief.md) — Business context, legacy system overview, 6-step solution ladder, simple HLD, requirements (`UR`, `FR`, `NFR`), back-of-the-envelope estimates, and 5-level Definition of Done
- [`docs/architecture.md`](docs/architecture.md) — High-Level Design (HLD), Low-Level Design (LLD), sequence & state diagrams, 7-layer defence-in-depth, and NFR/capacity mapping
- [`docs/roadmap.md`](docs/roadmap.md) — Phase 0 to Phase 8 vertical-slice delivery plan, open questions (`O1`–`O8` / `Q-1`–`Q-8`), and blocker register (`B1`–`B13`)
- [`docs/adr/`](docs/adr/) — Architecture Decision Records (`ADR-001` to `ADR-004`)
- [`AGENTS.md`](AGENTS.md) — Hard boundaries, approved stack, folder ownership, and engineering rules

## Guiding principles

Matches [`docs/project-brief.md`](docs/project-brief.md) (Section 7):

1. **Simplest solution first.** Redesign the process (`Step 0`), then deterministic rules (`Step 1`) or plain Python workflows (`Step 2`), before a single LLM call with RAG (`Step 3`), a single agent (`Step 4`), or a multi-agent team (`Step 5`).
2. **Humans decide the important things.** Job offers/hires, payments, payroll changes, contract changes, personal data deletion, and low-confidence cases always pause for a person (`Screen 2`).
3. **Least privilege.** Permissions are enforced by Keycloak (OIDC), OPA (`User ∩ Agent`), and MCP servers—never by prompts.
4. **Everything is recorded.** Every action (including denials) is logged with user, agent, inputs, outputs, policy decision, cost, and trace ID, and multi-agent runs are reviewed by an independent Auditor (Checker) agent.
5. **Measure it.** Quality (eval scores), latency, and token cost are tracked for every agent on the Heads-only Dashboard (`Screen 3`).
6. **Protect people's data.** UK GDPR by design, PII redaction before external models, local-model routing for sensitive data, and synthetic data only.
7. **Own the outcome.** Every live agent has a named human owner accountable for results, cost, and incidents.

## Repository layout

Matches [`AGENTS.md`](AGENTS.md), [`docs/architecture.md`](docs/architecture.md) (Section 7), and [`docs/project-brief.md`](docs/project-brief.md) (Appendix D):

```
services/agent-api        FastAPI app, OIDC/OPA middleware, router, rules engine, approval API
services/mcp-servers      MCP tool servers (HR, Ops, Finance), the only path to business systems
company-sandbox/          Pre-existing CareOps system (Laravel 12 + MySQL 8 + 21 SOPs, handed over running)
agents/<name>             One folder per helper: supervisor, recruitment, hr, ops, finance, auditor
evals/                    Golden sets, regression checks, ADR-001 benchmark, prompt-injection suite
infra/                    Docker Compose, OPA policies, Keycloak realms, Kubernetes, Terraform, k6
frontend/                 React + TypeScript + Tailwind (three screens only: Requests, Approvals, Dashboard)
docs/                     Project brief, architecture, roadmap, ADRs, compliance (DPIA, model card)
templates/agent-starter   Reusable starter pattern for building new bounded helpers safely
local/                    Personal notes, learning log & sandbox evaluation guides (gitignored)
```

## Key architecture decisions

| ADR | Decision |
|---|---|
| [ADR-001](docs/adr/ADR-001-router-first-multi-agent-where-justified.md) | 6-step solution ladder: process redesign, rules and workflows by default; multi-agent only where justified |
| [ADR-002](docs/adr/ADR-002-orchestration-framework.md) | LangGraph for stateful/pausable flows, plain Python for simple flows, rules engine for exact checks (no CrewAI/AutoGen) |
| [ADR-003](docs/adr/ADR-003-permissions-outside-the-model.md) | Permissions enforced outside the model in Keycloak, OPA (`User ∩ Agent`), and MCP servers |
| [ADR-004](docs/adr/ADR-004-simulated-existing-company.md) | Pre-existing CareOps system runs in `company-sandbox/`, accessed only via REST API (through MCP) and RAG |

## Roadmap (vertical slices)

We assume the existing CareOps legacy system (`company-sandbox/` running on `http://localhost:8080`) is already running and handed over to us. The platform roadmap (see [`docs/roadmap.md`](docs/roadmap.md)) progresses in vertical slices:

- [ ] **0. Setup & decisions:** toolchain, Compose wiring to `company-sandbox/`, Python/uv, ADRs, "before" task-time baseline
- [ ] **1. Walking skeleton:** FastAPI, Keycloak, OPA, first MCP server → `company-sandbox/` API, audit log, `Screen 1` (no LLM)
- [ ] **2. Slice A (Steps 1–2):** exact compliance & scheduling checks with rules engine & plain Python workflows (no LLM)
- [ ] **3. Slice B (Steps 3–4):** RAG with citations over `company-sandbox/sops/`, first LLM agent (CV screening), guardrails, LiteLLM & evals
- [ ] **4. Slice C (Step 0 + Approval):** Form HR-7B digital redesign + high-risk actions behind human approval (`Screen 2`)
- [ ] **5. Slice D (Step 5):** multi-agent cross-domain run (Supervisor + Workers) + independent Auditor agent + ADR-001 benchmark
- [ ] **6. Slice E:** HR & Finance flows + `templates/agent-starter` for mid-level engineers
- [ ] **7. Hardening:** prompt-injection red-team suite, observability (OpenTelemetry, Langfuse, Grafana), Heads-only Dashboard (`Screen 3`)
- [ ] **8. Release:** Docker/K8s/Terraform deployment, k6 load tests, UK GDPR compliance docs (DPIA, model card), before-vs-after report & demo

## Running locally

The pre-existing CareOps legacy system is packaged in [`company-sandbox/`](company-sandbox/README.md) (`http://localhost:8080/api/health`). Platform Docker Compose wiring comes in Phase 1.
