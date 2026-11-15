# CareOps Agent Platform: Project Brief

**Audience:** everyone (business, operations, management, compliance, engineering)
**Status:** Draft v0.2 (supersedes v0.1; needs owner approval)
**Note:** CareOps Ltd is a fictional company, modelled on a UK care-staffing business, used to demonstrate a real-world AI platform. All data is synthetic. Every number marked **(assumption)** is an illustrative planning figure to be replaced by measured values (see section 15).

**How to read this document**

| If you are... | Read |
|---|---|
| An executive or sponsor | Sections 1 to 5, 15, 16 |
| An operations, HR, recruitment or finance lead | Sections 1 to 3, 5, 8, 9 |
| Compliance or data protection | Sections 5.6, 11, 12, 13, 18 |
| An engineer | Everything, then `docs/architecture.md`, `docs/roadmap.md`, `docs/adr/` |

---

## 1. The Business

### 1.1 Who we are

CareOps Ltd supplies and manages care staff across the UK. We do four things, in this order:

1. **Recruit** carers (and check they are safe and legal to work).
2. **Keep them compliant** (background checks, right to work, mandatory training).
3. **Schedule them into shifts** for care homes and home-care providers (our clients).
4. **Pay them**, and **invoice our clients** for the hours worked.

Care is a people-sensitive, regulated sector. A mistake is not just an admin error: it can put a vulnerable person at risk, and personal data must be handled under UK GDPR.

### 1.2 Who we serve

| Group | Who they are | What they need from us |
|---|---|---|
| **Clients** | Care homes (`care_home`) and home-care providers (`domiciliary`) | The right, safe, checked carer on the right shift, and accurate invoices |
| **Carers** | `carer` and `senior_carer` staff (a senior carer can cover a standard shift, but not the other way round) | Fair shifts, correct pay on time, clear answers to HR questions |
| **Candidates** | People applying to become carers | A fast, fair response |
| **Office staff** | Recruitment, HR, Operations and Finance teams | Less repetitive admin, fewer mistakes |
| **Leadership** | Department heads and directors | Visibility of cost, speed, quality and risk |

### 1.3 The four teams

| Team | What they do |
|---|---|
| **Recruitment** | Find, screen and hire carers. Verify DBS and right-to-work. Book interviews. Only a Recruitment Lead or HR Director may authorise a formal job offer. |
| **HR** | Onboarding, annual, sick and unpaid leave, mandatory training, compliance records |
| **Operations** | Match carers to shifts, handle cancellations and no-shows, cover last-minute gaps |
| **Finance** | Check timesheets, invoice clients, run weekly payroll (cut-off Monday 12:00, bank payment Thursday) |

### 1.4 A typical week (to make it real)

- **Monday 09:00:** a care home phones Operations: its night carer has called in sick. A controller opens the roster, checks each candidate carer for valid DBS, valid right-to-work, no leave clash, no overlapping shift, and the right role level. This takes about 25 minutes and several phone calls **(assumption)**.
- **Monday 12:00:** payroll cut-off. Finance is still checking timesheets by eye, looking for duplicates and impossible hours.
- **Tuesday:** 80 new CVs have arrived. Recruitment reads them one by one. Some candidates wait days and accept a job elsewhere.
- **Thursday:** carers are paid. If a timesheet was wrong, payroll corrections follow.

---

## 2. Our Existing Systems (the "legacy" system, in plain English)

CareOps already runs on a company system that was built before AI arrived and **is handed over to us running**. We do not replace it. It lives in the folder `company-sandbox/` (see [company-sandbox/README.md](../company-sandbox/README.md) and [ADR-004](adr/ADR-004-simulated-existing-company.md)).

**Think of it like an old bank branch:**

| Part | Everyday meaning | Technical name |
|---|---|---|
| **The vault** | Where every record is kept (carers, shifts, timesheets, invoices...) in 13 linked tables | MySQL 8.0 database |
| **The back-office clerk** | Applies the company's rules when something is changed | Laravel 12 (PHP 8.4) application |
| **The teller window** | The only counter you may speak to; about 30 services (list candidates, assign a shift, approve a timesheet, create an invoice...) | REST API behind nginx, at `http://localhost:8080/api` |
| **The procedure manual** | 21 written company procedures (SOPs) for HR, Recruitment, Operations, Finance and General governance | Markdown files in `company-sandbox/sops/` |

**What the system already protects (12 write rules, R1 to R12).** When someone tries to change data, the clerk refuses unsafe changes. Examples: you cannot assign a carer whose DBS or right-to-work check is missing or expired on the shift date; you cannot double-book a carer; you cannot assign a carer who is on approved leave; you cannot approve a timesheet twice; you cannot hire a candidate unless they are at `offered` status with valid checks.

**What the system does not do well (this is the opportunity):**

1. **It does not tell you who is asking.** Every call uses one shared key (the service token). A recruiter, a finance manager and an AI helper look identical to it. Fine-grained permissions must therefore be added **outside** it.
2. **It only guards changes, it does not hunt for problems.** Mistakes that crept in before the rules existed still sit in the data (for example, expired checks against future shifts, overlapping bookings, duplicate timesheets, invoice totals that do not match their lines, duplicate client records).
3. **The written procedures are not tidy.** Two annual-leave policy versions sit side by side, DBS re-check frequency is ambiguous, and the hiring procedure still asks for a paper form (Form HR-7B) plus a manual voucher entry. Procedures were written for people to read, not for software to follow.
4. **It cannot read free text.** CVs are stored as unstructured text. Reading and judging them is manual work.
5. **No dashboards for leaders** on speed, cost, errors or workload.

**Rule for the platform:** we never touch the database directly. We only talk to the teller window (the API), and only through one controlled gateway (section 6).

---

## 3. The Business Problem

| # | Problem | Everyday example | Who feels it |
|---|---|---|---|
| P-1 | **Too much manual admin** | Reading CVs, chasing documents, checking dates, re-typing data between systems | All four teams |
| P-2 | **Slow response** | Candidates wait days and accept other jobs; cover is found by phone calls | Candidates, clients, Operations |
| P-3 | **Human error** | A missed DBS or right-to-work expiry; a duplicate or inflated timesheet; an invoice total that is wrong | Compliance, Finance, carers, clients |
| P-4 | **Hard to grow** | Doubling the business means doubling the admin headcount | Leadership |
| P-5 | **Little visibility** | Leaders cannot see where time is lost or what goes wrong | Department heads |
| P-6 | **Knowledge is buried in documents** | "What is our leave rule?" depends on who you ask and which version they read | HR, new staff |
| P-7 | **Compliance and data risk** | Personal and special-category data (such as DBS outcomes) handled by hand across systems | Information governance |

---

## 4. The Business Case

**Goal:** reduce routine admin effort, speed up hiring and cover, and lower compliance risk, **without removing human judgement from important decisions**.

### 4.1 What we will measure

| Benefit | Measure | Baseline (to be measured in Phase 0) | Target (to be confirmed after baseline) |
|---|---|---|---|
| Less admin time | Staff-hours per task, before vs after | See section 14.3 for planning figures | At least 50% fewer hours on automated tasks |
| Faster hiring | Working days from application to first response | Days (assumption) | Under 1 working day |
| Faster shift cover | Staff minutes to fill a last-minute gap | About 25 min (assumption) | Under 10 min |
| Fewer errors | Compliance misses and payroll corrections per month | Unknown | Zero expired checks on assigned shifts; corrections down by half |
| Controlled running cost | AI cost per completed task | n/a | Under £0.05 average |
| Trust and safety | Share of risky actions approved by a human | n/a | 100% |

### 4.2 Costs (headline)

Cloud hosting, AI model usage, and engineering time. Estimates are in section 14. **The demonstration build uses free and open-source tools and costs about £0 to run locally** (model usage is capped; see 14.8).

> The savings figures are **assumptions to be replaced with measured numbers**. Phase 0 starts by timing the current manual process so the "before" baseline is real, not guessed.

### 4.3 Risks we accept and manage

AI can be wrong, can be tricked by malicious text (for example, inside a CV), and can affect staff trust. See sections 11, 12 and 16.

---

## 5. The Proposed Solution: from Simple to Complex

**The idea in one sentence:** a platform of AI helpers ("agents") that do routine admin work, under strict safety rules, with humans in charge of important decisions, and **using the simplest tool that works for each problem, including no AI at all**.

We climb a six-step ladder. We only go up a step when the step below cannot do the job. Each step is justified, measured and recorded (see [ADR-001](adr/ADR-001-router-first-multi-agent-where-justified.md)).

| Step | What it is (plain words) | Uses AI? | Example at CareOps |
|---|---|---|---|
| **0. Redesign the process** | Fix the process before automating it | No | Replace the paper Form HR-7B step with a digital checklist; merge the two leave policies into one; clean up duplicate clients |
| **1. Rules** | Exact checks with a clear yes/no answer | No | "Is this DBS expired on the shift date?", "Do two shifts overlap?", "Are 24 hours claimed on an 8-hour shift?", "Does the invoice total equal its lines?" |
| **2. Fixed workflows** | A set recipe of steps, written as ordinary code | No | Leave request flow; "compliance expires in 30 days" reminder; Sunday-evening timesheet nudge before the Monday cut-off |
| **3. One AI call with company knowledge** | Ask a model one question, give it our policies, make it cite them | Yes (single call) | "What is our sickness-absence rule?" answered with a link to the policy; read a CV and return a structured summary |
| **4. One AI helper that uses tools** | A helper that can plan a few steps and use approved tools (look up a carer, list eligible carers) | Yes (single agent) | Recruitment helper screens 40 CVs, checks compliance status, proposes interview slots |
| **5. A team of AI helpers** | A coordinator splits a complex request and hands work to specialists; a checker reviews them | Yes (multi-agent) | "A carer has resigned: find cover, check payroll impact, tell the client" |

### 5.1 Step 0 and 1: Redesign first, then rules (no AI)

We do not just automate a bad process; we redesign it. Examples above show where the process itself is the problem. Fixed checks (dates, arithmetic, lookups, overlaps) are done by a **rules engine** and plain code, because they give exact, explainable answers with zero AI guesswork and zero AI cost. This alone removes a large share of the Finance and compliance work.

### 5.2 Step 2: Fixed workflows

For predictable tasks we use **plain Python state machines** (a checklist the software walks through step by step). They are cheap, fast, testable and easy to explain to an auditor. A **traffic controller** (the router, section 5.5) sends a request to the right workflow.

### 5.3 Step 3: One AI call with company knowledge (RAG)

For questions about policy, the platform searches our own documents first and then asks the model to answer **only from what it found, with a citation**. This is called **RAG** (retrieval-augmented generation). It lets the platform answer from the 21 company procedures using one database (PostgreSQL with pgvector). Answers must come back in a fixed, validated format (structured output), not free-form text.

### 5.4 Step 4 and 5: Agents and a team of agents

An **agent** is an AI helper with a narrow job, its own identity, its own small toolbox, its own memory, its own test set and its own budget. Agents can **plan** (decide the next step), **use tools** (call approved business functions), **remember** (short-term memory in Redis for the current conversation) and **delegate** (a coordinator hands sub-tasks to specialists).

| Helper | Job |
|---|---|
| **Recruitment** | Screens CVs, checks compliance status, proposes interviews |
| **HR** | Onboarding, leave, training reminders |
| **Operations** | Matches carers to shifts, finds cover |
| **Finance** | Validates timesheets, flags invoice and payroll issues |
| **Coordinator (Supervisor)** | Splits complex requests and hands out the work |
| **Checker (Auditor)** | Reviews what the other helpers did. It can never review its own actions |

Multi-agent is used **only** for open-ended, cross-department tasks, because it costs more, is slower and is harder to control. We will prove it is worth it by measuring it against the simpler options (ADR-001 benchmark).

### 5.5 The traffic controller and confidence-based routing

Every request first meets a **router** that decides: exact check, fixed workflow, or AI helpers. It also gives a **confidence score**. If it is not sure, the request is **not guessed at**; it goes to a person in the approval queue. We start with a simple rule-based router and only add an AI classifier if the rules prove insufficient.

### 5.6 Safety, control and trust (built in, not bolted on)

| Control | In plain words |
|---|---|
| **Least access** | Each helper can only use the few tools its job needs, and only with the permissions of the person who asked (the overlap of the user's rights and the helper's rights) |
| **Permissions outside the AI** | A prompt saying "do not access payroll" is a request, not a lock. The real lock is the login system (Keycloak) and the policy engine (OPA), checked at the gateway ([ADR-003](adr/ADR-003-permissions-outside-the-model.md)) |
| **Safe gateway (MCP)** | One controlled doorway to each business system. It checks the policy, validates the request, writes a log entry, and is the only holder of the legacy system's shared key |
| **Human approval** | Sending a job offer, approving a payment, changing payroll or a contract, or deleting personal data **always** pauses and waits for a person with the right role |
| **Prompt-injection defence** | CVs, emails and documents are treated as untrusted text. Hidden instructions inside them are ignored, screened for, and tested against |
| **Privacy protection** | Personal data is masked before going to an outside model. Sensitive work uses a local model |
| **Budgets and timeouts** | Every run has a maximum number of steps, a spending cap and a time limit |
| **Safe retries** | Actions that change data are designed so that a retry can never pay twice or book twice (idempotency) |
| **Agents that audit agents** | A separate Checker helper reviews the others. Self-checking steps validate outputs before anything is acted on |
| **Full logbook** | Who, what, inputs, outputs, policy decision, cost and trace ID for every action |
| **Quality tests** | Fixed test sets and regression checks run automatically, so quality cannot silently get worse. Thresholds are never lowered to make a test pass |

### 5.7 Choosing models (vendor and cost strategy)

All model calls go through one **gateway (LiteLLM)**. This gives us: the right model for each task (small and cheap for simple work, stronger for hard work), **automatic fallbacks** if a provider fails, **spending caps**, latency tracking, and control of **data location** (sensitive data stays on a local model). No agent code ever talks to a model provider directly, so switching vendors is a configuration change.

| Task type | Model class (illustrative) | Why |
|---|---|---|
| Simple classification, short explanations | Small, cheap model | Fast and low cost |
| CV reading, policy answers, agent planning | Mid-tier model | Good accuracy at reasonable cost |
| Anything containing unmasked personal data | Local model | Data does not leave our environment |
| Fallback | Second provider or local model | Keeps the service running |

The specific local model and embedding model are open decisions (see section 19).

### 5.8 Frameworks: when to use one, and when not to

- **LangGraph** for multi-step, stateful flows that must pause for human approval and resume ([ADR-002](adr/ADR-002-orchestration-framework.md)).
- **Plain Python** for simple flows. Not every problem needs a framework.
- **CrewAI and AutoGen** were considered as comparable frameworks and **deliberately not used** in this project; the reasons are recorded in ADR-002 and they can be explored in a separate project.

### 5.9 Reuse: patterns for other engineers

A **starter template** (`templates/agent-starter`) captures the standard pattern: narrow job, own identity, minimal tools, prompts in versioned files, typed inputs and outputs, eval set, budget, audit hooks. Mid-level engineers can then build a new agent safely and quickly. Standards for agent development are published in the architecture document and ADRs.

### 5.10 Connecting to real business systems

The platform wraps what exists: a legacy PHP/MySQL back end (ERP/HR/CRM-style functions). The same gateway pattern (MCP) can later wrap a workflow platform or other systems. We never rewrite the legacy business rules; we call them and respect their answers.

### 5.11 What leadership gets: screens

Exactly three screens (kept deliberately small):

1. **Request screen:** staff submit tasks and watch progress.
2. **Approval and escalation queue:** humans approve or reject risky or uncertain actions.
3. **Heads-only dashboard:** success rate, cost per agent, latency, escalations, audit log and evaluation results.

### 5.12 Beyond today's roadmap (ideas to challenge and extend)

These are proposals, to be backed by evidence before they are scheduled:

- **Proactive compliance:** warn 30 days before a check expires instead of reacting.
- **Data clean-up agent:** propose merges for duplicate clients and fixes for known bad records (always human-approved).
- **Shift demand forecasting** from past shift patterns, to pre-book cover.
- **Procedure health check:** flag contradictory or out-of-date SOPs (for example, two leave policy versions).
- **Carer self-service assistant** for payslip, leave and shift questions (needs the "thousands of users" design in section 14).

---

## 6. High-Level Design (Simple View)

```
   +-------------------------------------------------------+
   |  PEOPLE:  Staff    Team Leads    Department Heads     |
   +---------------------------+---------------------------+
                               |
                               v
   +-------------------------------------------------------+
   |  WEB APP:  Requests | Approval Queue | Dashboard       |
   +---------------------------+---------------------------+
                               |
                               v
   +-------------------------------------------------------+
   |  FRONT DOOR:  Who are you? What are you allowed to do?|
   |               (Keycloak login + OPA permissions)      |
   +---------------------------+---------------------------+
                               |
                               v
   +-------------------------------------------------------+
   |  SAFETY GUARD (in):  Screen text, mask personal data  |
   +---------------------------+---------------------------+
                               |
                               v
   +-------------------------------------------------------+
   |  TRAFFIC CONTROLLER:  What kind of task? How sure?    |
   +-------+---------------------+----------------+--------+
           |                     |                |
   exact   |             simple  |        complex |   not sure
           v                     v                v      |
   +--------------+  +--------------------+  +---------------------+
   | RULES        |  | FIXED WORKFLOWS    |  | TEAM OF AI HELPERS  |
   | (no AI)      |  | (set recipes,      |  | Recruitment | HR    |
   |              |  |  no/low AI)        |  | Operations | Fin.   |
   |              |  |                    |  | Coordinator+Checker |
   +------+-------+  +---------+----------+  +----------+----------+
          |                    |                        |
          |                    |         +--------------+-------------+
          |                    |         | Model gateway (LiteLLM)    |
          |                    |         | Company knowledge (RAG)    |
          |                    |         +--------------+-------------+
          +--------------------+------------------------+
                               |            risky action, or not sure
                               |                   |
                               |                   v
                               |       +-------------------------+
                               |       | HUMAN APPROVAL QUEUE    |
                               |       | (offers, payments, low  |
                               |       |  confidence)            |
                               |       +-----------+-------------+
                               v                   |
   +-------------------------------------------------------+
   |  SAFE GATEWAY (MCP):  The only way into company       |
   |  systems. Checks policy, logs everything.             |
   +---------------------------+---------------------------+
                               |
                               v
   +-------------------------------------------------------+
   |  EXISTING COMPANY SYSTEMS (legacy, handed over):      |
   |  Laravel API + MySQL (HR | Operations | Finance | CRM) |
   |  + 21 written procedures (SOPs)                       |
   +-------------------------------------------------------+

   ALWAYS WORKING ALONGSIDE:
   [ Safety Guard (out) ]  [ Human Approval for risky actions ]
   [ Logbook of every action ]  [ Cost, speed and quality dashboard ]
   [ Automatic quality tests ]
```

**Reading the diagram, step by step**

| Step | What happens | Why it matters |
|---|---|---|
| People and web app | Staff ask for help through three simple screens | Easy to use, nothing to install |
| Front door | Checks identity and permissions | Nobody sees or does more than their role allows |
| Safety guard (in) | Screens untrusted text and masks personal data | Stops tricks hidden in CVs and protects privacy |
| Traffic controller | Sends exact checks to rules, simple tasks to recipes, complex ones to the AI team, unsure ones to a person | Cheaper, faster and safer than using AI for everything |
| Rules, workflows, AI helpers | Do the routine work, each with a narrow job | One helper going wrong cannot reach everything |
| Model gateway and knowledge search | Right model per task, with cost caps; answers cite company policy | Control over cost, data location and accuracy |
| Human approval queue | Risky or uncertain actions stop here | Humans stay in control |
| Safe gateway | Controls what each helper can touch | Rules are enforced by the system, not by trusting the AI |
| Existing systems | The real data and records | Existing systems stay in place; we connect to them |
| Always-on items | Safety, logbook, dashboard, tests | Everything can be audited, costs stay visible, quality stays stable |

---

## 7. Guiding Principles

1. **Simplest solution first.** Redesign the process, then rules or plain code, before AI. AI before multiple AI helpers.
2. **Humans decide the important things.** Offers, payments and uncertain cases always go to a person.
3. **Least access.** Each helper gets only the permissions its job needs.
4. **Everything is recorded.** Every action can be traced and explained afterwards.
5. **Measure it.** Quality, speed and cost are tracked for every helper.
6. **Protect people's data.** UK GDPR and responsible AI practice are designed in from the start.
7. **Own the outcome.** The team is accountable for results, cost and incidents, not only for code.

---

## 8. Stakeholders and User Requirements

### 8.1 Stakeholders and roles

| Role | Who | System role |
|---|---|---|
| Staff | Recruitment, HR, Operations, Finance team members | `staff` |
| Team Lead | Approves routine escalations and reviews team results | `team_lead` |
| Department Head | Sees performance, cost and the audit log; approves high-risk actions | `department_head` |
| Information governance / compliance | Reviews data protection documents and audit evidence | Read access to audit log (to be confirmed) |
| Engineers | Build and run the platform | Not a business role |
| AI agents | Each has its own identity | Service identities, never a person's login |

Per the hiring procedure, formal job offers can only be authorised by a Recruitment Lead or HR Director; the approval role model must reflect this.

### 8.2 User requirements (what people need, in their words)

| ID | As a... | I want to... | So that... |
|---|---|---|---|
| UR-1 | Recruiter | upload or select CVs and get a ranked, explained screening result with the policy it was judged against | I can reply to good candidates the same day |
| UR-2 | Recruiter | see a candidate's DBS and right-to-work status at once | I never progress someone with missing checks |
| UR-3 | Recruitment Lead | approve or reject a proposed offer in one queue | offers are always my decision |
| UR-4 | HR officer | ask "what is our leave / sickness / training rule?" and get an answer with the policy cited | I stop searching documents and arguing over versions |
| UR-5 | HR officer | be warned before a carer's check or training expires | nobody works with an expired check |
| UR-6 | Operations controller | enter a gap and get a short list of eligible, compliant, available carers | I fill cover in minutes, not through phone calls |
| UR-7 | Operations controller | see why a carer was or was not suggested | I trust and can explain the choice |
| UR-8 | Finance officer | have timesheets pre-checked for duplicates and impossible hours | I only review the odd ones |
| UR-9 | Finance officer | have invoice totals checked against their lines before sending | clients get correct invoices |
| UR-10 | Finance Head | approve payment-related actions explicitly | no money moves without a person |
| UR-11 | Any staff member | submit a request in plain language and watch its progress | I know what is happening and what is waiting on me |
| UR-12 | Team Lead | review uncertain items in an escalation queue | uncertain cases are handled by people |
| UR-13 | Department Head | see success rate, cost per helper, speed, escalations, audit log and test results on one dashboard | I can steer the programme and spot problems |
| UR-14 | Compliance officer | trace any action to a person, a helper, a policy decision and a cost | I can answer an audit or a data request |
| UR-15 | Engineer | copy a starter template to build a new helper safely | I add capability without weakening controls |

---

## 9. Functional Requirements

Priority: **M** = must, **S** = should, **C** = could. "Slice" refers to the roadmap in [`roadmap.md`](roadmap.md).

| ID | Requirement | Priority | Slice | Traces to |
|---|---|---|---|---|
| FR-1 | Users sign in with an organisation login (OIDC) and receive a role (`staff`, `team_lead`, `department_head`) | M | 1 | UR-11 |
| FR-2 | Every request and tool call is authorised by policy as the overlap of user and helper permissions | M | 1 | UR-14 |
| FR-3 | Staff can submit a request and view live status (received, working, waiting for approval, done, escalated, rejected) | M | 1 | UR-11 |
| FR-4 | Look up a carer's or candidate's compliance status (DBS, right to work, moving and handling, safeguarding) via the gateway | M | 1 | UR-2 |
| FR-5 | Rules engine finds expired or soon-to-expire checks, including those on future shifts (30-day window) | M | 2 | UR-5 |
| FR-6 | Rules engine detects overlapping shifts, shifts during approved leave, duplicate timesheets, excessive hours, past-dated open shifts and invoice-total mismatches | M | 2 to 6 | UR-8, UR-9 |
| FR-7 | Rule-based router sends requests to exact checks or fixed workflows | M | 2 | UR-11 |
| FR-8 | Policy questions are answered from the 21 procedures using RAG, with file and section citations | M | 3 | UR-4 |
| FR-9 | Recruitment helper screens CVs against the screening criteria and returns a validated, structured, cited result with a confidence score | M | 3 | UR-1 |
| FR-10 | Low-confidence results are placed in the approval and escalation queue instead of acting | M | 3, 4 | UR-12 |
| FR-11 | High-risk actions (job offer or hire, payment, payroll change, contract change, personal data deletion) pause and require approval by a person with the right role, then resume | M | 4 | UR-3, UR-10 |
| FR-12 | Approval queue screen lets an approver see the proposal, evidence and risk, and approve or reject with a reason | M | 4 | UR-3, UR-12 |
| FR-13 | Operations helper lists eligible carers for a shift and explains why each is eligible or not | M | 5 | UR-6, UR-7 |
| FR-14 | Coordinator splits a cross-department request, delegates to helpers and merges the result | M | 5 | UR-6 |
| FR-15 | Checker helper independently reviews every multi-helper run and can flag or escalate | M | 5 | UR-14 |
| FR-16 | HR flows: leave request, onboarding checklist, training and expiry reminders | S | 6 | UR-5 |
| FR-17 | Finance flows: timesheet validation, invoice pre-check, payroll cut-off reminder | S | 6 | UR-8, UR-9 |
| FR-18 | Every action is written to an audit log (user, helper, tool, inputs, outputs, policy decision, cost, trace ID), including denials | M | 1 onward | UR-14 |
| FR-19 | State-changing tools are idempotent (safe to retry) | M | 2 | UR-10 |
| FR-20 | Every run is bounded by a maximum number of steps, a cost cap and a time limit | M | 3 | UR-13 |
| FR-21 | Input screening for prompt injection and personal-data masking before any external model call | M | 3 | UR-14 |
| FR-22 | Model calls go through the gateway with fallbacks, spending caps and per-agent cost tracking | M | 3 | UR-13 |
| FR-23 | Heads-only dashboard: success rate, cost per helper, latency, escalations, audit log, evaluation results | M | 7 | UR-13 |
| FR-24 | Automated evaluation suite (golden sets, regression, prompt-injection set) runs on demand and in CI | M | 3, 7 | UR-14 |
| FR-25 | Starter template for building a new helper with the standard controls | S | 6 | UR-15 |
| FR-26 | Memory: short-term conversation context per run, expiring automatically | S | 3 to 5 | UR-11 |
| FR-27 | Proactive suggestions (data clean-up, SOP health check, demand forecast) | C | Later | section 5.12 |

---

## 10. Non-Functional Requirements

Targets are planning values **(assumption)** until measured, but each is testable.

| ID | Area | Requirement | How verified |
|---|---|---|---|
| NFR-1 | **Security** | No agent holds database or API credentials. Only the gateway holds the legacy service key. Database port is never reachable by agents | Policy tests, network test, code review |
| NFR-2 | **Security** | 100% of tool calls are checked by policy; a tool without a policy fails the build | CI check |
| NFR-3 | **Safety** | Zero successful unauthorised actions in the prompt-injection suite | Injection suite in CI |
| NFR-4 | **Human oversight** | 100% of high-risk actions executed only after a recorded human approval | Audit review, tests |
| NFR-5 | **Accuracy** | CV screening agrees with human-labelled golden set at or above 85%; citations correct at or above 95% | Eval suite |
| NFR-6 | **Reliability** | At least 90% task success on the golden set for each live helper; escalation rate tracked (expected 10% to 20% early on) | Eval suite, dashboard |
| NFR-7 | **Performance** | Exact checks and lookups: p95 under 2 seconds. Single AI call: p95 under 15 seconds. Multi-helper run: p95 under 120 seconds (excluding time waiting for a human) | k6 load tests, traces |
| NFR-8 | **Availability** | 99% during working hours 07:00 to 20:00 UK time (to be confirmed; cover requests may occur out of hours) | Monitoring |
| NFR-9 | **Scalability** | Design for tens of users now, hundreds within a year, thousands later (section 14.1), without redesign: stateless API, queue-based work, cacheable lookups | Load tests at 10x current peak |
| NFR-10 | **Cost** | Average AI cost per completed task under £0.05; per-run cap £0.50; monthly AI budget alarm at 80% | Dashboard, gateway caps |
| NFR-11 | **Auditability** | Every action is reconstructable from the log in under 5 minutes by trace ID. Audit records cannot be edited | Audit drill |
| NFR-12 | **Observability** | One request produces one trace across all services; per-helper latency, cost, errors visible | Tracing, dashboard |
| NFR-13 | **Privacy** | Synthetic data only in this project; personal data masked before external models; sensitive data on the local model route | Redaction tests |
| NFR-14 | **Data retention** | Retention periods configurable per record type; values to be confirmed `TODO: verify` | Config review |
| NFR-15 | **Maintainability** | Typed Python (`mypy --strict`), formatted and linted, prompts in versioned files, config via environment only | CI |
| NFR-16 | **Testability** | Unit tests for logic, policy tests for every role and helper pair, an eval case for every prompt, model or agent change | CI |
| NFR-17 | **Portability** | Whole stack runs locally with one command (Docker Compose); production manifests (Kubernetes, Terraform) later | Compose run |
| NFR-18 | **Usability** | Exactly three screens; a new staff member can submit a request with no training | Walk-through test |
| NFR-19 | **Vendor independence** | Switching a model provider requires configuration only, no code change | Gateway test |
| NFR-20 | **Recoverability** | A paused or interrupted run resumes from its last checkpoint; no duplicate side effects on retry | Resume test |

---

## 11. Compliance and Responsible AI

| Topic | Approach |
|---|---|
| UK GDPR and Data Protection Act 2018 | Designed in from the start: purpose limitation, minimal data, access control, audit trail, masking. **TODO: verify** all legal detail with a qualified adviser before any real use |
| DPIA (data protection impact assessment) | A DPIA document is a deliverable (Phase 8). Draft only; legal review required |
| Automated decisions about people | Candidate screening and similar tasks are **recommendations**; a person makes the final decision. **TODO: verify** the rules on solely automated decisions with a qualified adviser |
| Special-category data | DBS outcomes and fit notes are handled with extra care: masked for external models, local-model route, role-restricted |
| Model card and responsible-AI notes | Plain-language description of each helper's purpose, limits, tests and known risks |
| Fairness | Screening criteria are limited to the written SOP criteria; bias checks are part of the eval set. **TODO: verify** with an adviser |
| Incident handling | Incidents involving agents follow the company escalation procedure; an owner is named for each live helper |
| Retention and deletion | Deleting personal data is a high-risk action requiring human approval. Retention periods are `TODO: verify` |

---

## 12. Second-Order Effects and Change Management

We do not only ask "what can be automated?" but also "what changes when we do it?"

| Effect | What may happen | What we do |
|---|---|---|
| **Workload shifts** | Routine work shrinks, but review and approval work grows and exceptions get harder | Measure review time; protect approvers from overload with thresholds |
| **Accountability** | "The AI did it" is never an acceptable answer | Named human approver and owner for every action and every helper |
| **Compliance** | Faster processes can mean faster mistakes | Hard approval gates, checker helper, audit trail |
| **Staff trust** | Staff may fear replacement or distrust results | Involve teams early, show the reasoning and sources, measure satisfaction, retrain rather than replace |
| **Skill drift** | People stop practising what the AI does | Sample reviews, periodic manual checks |
| **Dependency** | Overreliance on one vendor or model | Gateway, fallbacks, local model |
| **Process change** | Redesigned steps (such as dropping the paper form) need policy owners to agree | Owner decisions recorded in ADRs |

---

## 13. Scope, Assumptions, Constraints, Dependencies

**In scope:** the platform (API, helpers, rules, gateway, policies, screens, evaluations, observability, compliance documents) on top of the existing system.

**Out of scope:** rewriting the legacy system, real personal data, a mobile app, extra screens, carer self-service (future), real payments or real offers sent to real people.

**Constraints (hard rules from `AGENTS.md`):** approved stack only; no CrewAI or AutoGen; never enforce permissions in prompts; no direct credentials for agents; human approval for high-risk actions; synthetic data only; no secrets committed.

**Assumptions**
- The legacy system stays as it is and keeps running at `http://localhost:8080`.
- Volumes and costs in section 14 are illustrative and will be replaced by measurements.
- Model access is via free tiers or a local model for the demonstration.

**Dependencies:** a Python 3.12 setup, Docker, a chosen local model and embedding model, owner decisions on the open items in section 19.

---

## 14. Back-of-the-Envelope Estimates

> These are **planning estimates built from stated assumptions**, so that sizing, cost and payback can be discussed credibly. Every input is listed so it can be changed.

### 14.1 Users and scale

| Stage | Office users | Other users | Requests per user per day | Requests per day | Busiest hour (20% of day) | Design headroom (x10) |
|---|---|---|---|---|---|---|
| Today (pilot) | 10 (4 teams) | None | 30 | 300 | 60 per hour (0.02 per second) | 0.2 per second |
| In 12 months | 100 | None | 30 | 3,000 | 600 per hour (0.17 per second) | 1.7 per second |
| Thousands (future, carer self-service) | 100 | 2,000 carers at 3 per day | n/a | 9,000 | 1,800 per hour (0.5 per second) | 5 per second |

**AI runs.** Assume 10% of requests need an AI helper: 30, 300, and 900 runs per day. At about 30 seconds per run, the busiest hour at the highest stage needs 180 runs × 30 s = 5,400 s, about 1.5 runs in parallel on average. **Design for 10 parallel runs** for headroom.

**Load-test target (k6):** 10 requests per second sustained, 25 per second spike, p95 within NFR-7.

**Storage.** Audit record about 2 KB per tool call, about 3 tool calls per request:

| Stage | Per day | Per year |
|---|---|---|
| Today | 1.8 MB | about 0.7 GB |
| 12 months | 18 MB | about 6.6 GB |
| Thousands | 54 MB | about 20 GB |

Knowledge store: 21 short procedures, a few hundred text chunks, under 5 MB. Trivial.

### 14.2 Monthly business volumes (assumption, sized for about 250 active carers and about 45 clients)

| Item | Per month |
|---|---|
| Job applications (CVs) | 400 |
| Shifts | 3,000 |
| Timesheets | 3,000 |
| Client invoices (weekly per client) | 180 |
| Last-minute cover requests | 150 |
| Policy questions | 1,000 |
| HR queries (leave, onboarding, training) | 1,500 |

### 14.3 Staff time saved

| Task | Volume | Manual time | With platform | Hours saved per month |
|---|---|---|---|---|
| CV screening | 400 | 10 min each = 66.7 h | 2 min human review = 13.3 h | 53.3 |
| Compliance expiry tracking | continuous | 26 h | 2 h (rules) | 24.0 |
| Shift cover | 150 | 25 min each = 62.5 h | 8 min = 20 h | 42.5 |
| Timesheet checking | 3,000 | 3 min each = 150 h | 80% auto-passed by rules; 600 reviewed = 30 h | 120.0 |
| Invoice preparation and check | 180 | 20 min each = 60 h | 6 min = 18 h | 42.0 |
| HR queries and reminders | 1,500 | 4 min each = 100 h | 60 h | 40.0 |
| Policy questions | 1,000 | 5 min each = 83.3 h | 1.5 min = 25 h | 58.3 |
| **Total (gross)** | | | | **about 380 hours** |

Not all saved time becomes real saving. Apply a **60% realisation factor** (assumption): about **228 hours per month**, equal to about **1.5 full-time staff** (at 150 productive hours per person per month).
Value at an assumed loaded cost of **£15 per hour**: **about £3,400 per month**.

Note that rules, not AI, deliver the biggest block (timesheets and compliance: 144 of 380 hours). This supports the "simplest solution first" principle.

### 14.4 AI model cost (assumption: mid-tier model £2.50 per million input tokens and £12 per million output tokens; small model £0.20 and £1.00)

| Task | Monthly volume | Tokens per task (in / out) | Model | Monthly cost |
|---|---|---|---|---|
| CV screening | 400 | 4,000 / 600 | Mid | £6.88 |
| Router (only ambiguous requests) | 1,000 | 600 / 80 | Small | £0.20 |
| Shift cover (multi-helper run) | 150 | 30,000 / 4,000 | Mid | £18.45 |
| Checker review of those runs | 150 | 10,000 / 1,000 | Mid | £5.55 |
| Policy questions (RAG) | 1,000 | 3,000 / 400 | Mid | £12.30 |
| HR and finance explanations | 1,500 | 2,000 / 300 | Small | £1.05 |
| **Total** | **4,200 AI tasks** | | | **about £45 per month** |

- **Cost per AI task:** £45 ÷ 4,200 = **about £0.011** (target is under £0.05).
- **Budget with safety margin (3x for retries and growth): about £135 per month.**
- **Average multi-helper cover run:** (£18.45 + £5.55) ÷ 150 = about £0.16, below the £0.50 per-run cap.
- The same work done by staff costs about £3,400 per month (14.3). **The model bill is roughly 1% of the value created.**

### 14.5 Running infrastructure (production-like, assumption)

| Item | Monthly |
|---|---|
| Application host (about 4 vCPU, 16 GB) running API, gateway, policy engine, login, monitoring | £80 |
| Managed PostgreSQL (with vector search) and backups | £60 |
| Redis (managed or small instance) | £20 |
| Monitoring, logs, storage and network | £40 |
| Pilot environment and spare capacity | £50 |
| **Total infrastructure** | **about £250** |

Pilot run cost: £250 + £135 AI budget = **about £385 per month**.

### 14.6 Build effort (from the roadmap phases)

| Phase | Effort (person-weeks, one senior engineer) |
|---|---|
| 0 Setup and decisions | 1 |
| 1 Walking skeleton | 3 |
| 2 Rules, no AI | 2 |
| 3 First AI helper with knowledge search | 4 |
| 4 Human approval | 3 |
| 5 Multi-helper and Checker | 3 |
| 6 HR and Finance flows, starter template | 2 |
| 7 Hardening, evaluations, dashboard | 3 |
| 8 Release, load tests, compliance documents, demo | 2 |
| **Total** | **23 person-weeks, about 5.3 months** |

With one senior and one mid-level engineer, including reviews and mentoring, plan about **12 person-months**. At an assumed blended cost of **£5,000 per person-month**, build cost is **about £60,000**.

### 14.7 Payback

| Scenario | Monthly saving | Monthly run cost | Net per month | Payback on £60,000 |
|---|---|---|---|---|
| Today's size (250 carers) | £3,420 | £385 | £3,035 | about 20 months |
| Business grows 3x without growing admin staff | £10,260 | about £805 | £9,455 | about 6 months |

**Honest reading:** at today's size the saving alone is modest. The strong case is **growth** (P-4) plus benefits not priced here: fewer compliance incidents, faster hiring (candidates not lost), faster cover (clients kept), and better visibility. These benefits will be priced once the baseline is measured.

### 14.8 Cost of this demonstration (free resources)

This is a functional demonstration, so we use free and open-source resources wherever possible (licence terms to be checked, `TODO: verify`).

| Item | Resource | Cost |
|---|---|---|
| Hosting | Local machine with Docker Compose | £0 |
| Orchestration, API, data | LangGraph, FastAPI, PostgreSQL with pgvector, Redis | £0 (open source) |
| Login and policy | Keycloak, OPA | £0 (open source) |
| Monitoring | OpenTelemetry, Grafana, Langfuse (self-hosted) | £0 |
| Evaluations | Promptfoo, DeepEval | £0 |
| Models | Provider free tiers and/or a local model (choice pending, see section 19) | £0 to a small capped amount |
| Legacy system | Provided running (`company-sandbox/`) | £0 |
| Engineering time | Project owner with AI coding assistant | Not priced |

A hard **monthly cap** is set in the gateway so that the demonstration cannot overspend.

---

## 15. Measuring Success (baseline first)

1. **Phase 0 baseline:** time each manual task for a sample week, record the figures in a short table, and replace the assumptions in section 14.
2. **During build:** every helper has an evaluation set; results show on the dashboard.
3. **After release:** compare before and after for each measure in section 4.1 and report it to leadership.

---

## 16. Risks

| ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-1 | AI gives a wrong answer | Medium | High | Confidence routing, human approval, Checker, evaluations |
| R-2 | Prompt injection in a CV or email | Medium | High | Untrusted-text rules, permission enforcement outside the model, injection test suite |
| R-3 | Personal data leaks to an outside model | Low | High | Masking, local-model route, synthetic data in this project |
| R-4 | Cost overruns | Medium | Medium | Per-run caps, monthly cap, gateway tracking, dashboard alarm |
| R-5 | Staff distrust or resistance | Medium | Medium | Early involvement, transparency, training (section 12) |
| R-6 | Contradictory procedures give inconsistent answers | High | Medium | Procedure clean-up (step 0), cite sources, flag conflicts to a person |
| R-7 | Over-engineering with agents where rules suffice | Medium | Medium | Ladder in section 5, ADR-001 benchmark |
| R-8 | Vendor or model outage | Medium | Medium | Fallbacks, local model |
| R-9 | Legal or regulatory misunderstanding | Medium | High | Mark `TODO: verify`, get qualified review |
| R-10 | Scope creep (extra screens or features) | Medium | Medium | Three-screen limit, scope control |
| R-11 | The legacy system's shared key is exposed | Low | High | Only the gateway holds it; never in prompts or code; secrets not committed |
| R-12 | Savings assumptions prove wrong | Medium | Medium | Baseline in Phase 0, re-run estimates |

---

## 17. Delivery Plan

### 17.1 Phases (summary)

| Phase | Outcome |
|---|---|
| 0. Setup and decisions | Tooling, open decisions closed, "before" baseline measured |
| 1. Walking skeleton | One simple request travels end to end: screen, login, permissions, gateway, existing system, logbook |
| 2. Rules (no AI) | Compliance and data-quality checks with no AI at all |
| 3. First AI helper | CV screening and policy answers with citations, safety checks and a first evaluation set |
| 4. Human approval | Risky actions pause for a person (job offer or hire) |
| 5. Team of helpers | Cross-department request, Coordinator and Checker |
| 6. HR and Finance flows | More flows and the starter template |
| 7. Hardening | Injection suite, monitoring, cost reports, dashboard |
| 8. Release | Deployment, load tests, compliance documents, demo |

### 17.2 What the first six months must deliver

1. **Publish the target architecture and standards** for agent development, and get them used (architecture document, ADRs, starter template).
2. **Ship one working multi-helper workflow** that replaces a substantial block of admin effort (for example, last-minute shift cover).
3. **Stand up evaluation, monitoring and cost reporting** for every live helper.
4. **Mentor engineers and raise delivery quality** measurably (design reviews, template adoption, fewer defects).
5. **Show the commercial value** with measured before and after figures (section 15).

---

## 18. Definition of Done

### 18.1 For any task

- [ ] Code runs locally via Docker Compose
- [ ] Unit tests, policy tests and relevant evaluations pass
- [ ] Lint, format and type checks pass
- [ ] Audit logging and tracing are in place for new helper or tool behaviour
- [ ] No secrets and no real personal data
- [ ] Documentation updated (README, ADR if needed, `local/learning-log.md`)
- [ ] Anything uncertain is flagged (`TODO: verify`), not hidden

### 18.2 For a tool (gateway)

- [ ] Input schema defined and validated
- [ ] Policy written and policy tests cover allow and deny cases
- [ ] Audit call present (including denials)
- [ ] Idempotent if it changes data
- [ ] Test present

### 18.3 For an AI helper

- [ ] Narrow job, own identity, minimal toolset
- [ ] Prompts in versioned files; structured, validated output
- [ ] Budget set (steps, cost, time)
- [ ] Confidence routing to the approval queue
- [ ] Own evaluation set, passing agreed thresholds (thresholds never lowered to pass)
- [ ] Injection tests pass; personal data masked
- [ ] Cost per task visible on the dashboard
- [ ] A named human owner

### 18.4 For a slice (roadmap phase)

- [ ] Works end to end through the real layers (screen, API, permissions, gateway, existing system, logbook)
- [ ] Demonstrated to the project owner with a short written explanation in plain language
- [ ] Measured against the simpler alternative where relevant (ADR-001)

### 18.5 For the project (release)

- [ ] All three screens working with role checks done by the API
- [ ] A high-risk action cannot complete without a recorded human approval (tested)
- [ ] Zero successful unauthorised actions in the injection suite
- [ ] Load test passes the targets in section 14.1
- [ ] Evaluation, monitoring and cost reports exist for every live helper
- [ ] DPIA draft, UK GDPR mapping and model card delivered and flagged for legal review
- [ ] Before and after measurements reported to leadership
- [ ] Demo recorded and write-up published

---

## 19. Open Questions (owner decisions needed)

Cross-referenced with [`docs/roadmap.md`](roadmap.md) Open Items (`O1`–`O8`) and Blocker Register (`B1`–`B13`).

| # | Question | Roadmap link | Needed by |
|---|---|---|---|
| Q-1 | Which local model, and which embedding model, will we use? (record in ADR-008) | `O6`, `O7`, `B7` | Phase 0 / Phase 3 |
| Q-2 | Which external model providers and what monthly cap? | `O5`, `B6` | Phase 3 |
| Q-3 | DBS re-check frequency: SOPs are ambiguous (annual vs 3-year). Which rule applies? `TODO: verify` | Task 2.1, `B5` | Phase 2 |
| Q-4 | Which of the two annual-leave policy versions (`annual-leave-policy-v1.md` vs `annual-leave-policy.md`) is current? | Task 3.5, 6.4, `B8`, `B10` | Phase 3 / Phase 6 |
| Q-5 | May we replace the paper Form HR-7B step with a digital checklist (Step 0 process redesign)? | Task 4.4, `B9` | Phase 4 |
| Q-6 | Approval role model: who approves which high-risk action (offers, payments, payroll, contracts, deletions)? | Task 1.3, 4.4, `B4`, `B9` | Phase 1 / Phase 4 |
| Q-7 | Real baseline times for the manual tasks in section 14.3 | Task 0.10 | Phase 0 |
| Q-8 | Working hours (07:00–20:00 UK time) and out-of-hours expectations for availability targets (`NFR-8`) | Task 8.6, `B12` | Phase 1 / Phase 8 |

---

## Appendix A: Plain-English Glossary

| Term | Meaning |
|---|---|
| **Agent** | An AI helper with a narrow job, its own identity and toolbox |
| **Multi-agent** | A coordinator plus specialist helpers working together |
| **LLM** | Large language model: the AI that reads and writes text |
| **Prompt** | The instructions given to a model |
| **Prompt injection** | A trick where hidden text in a document tries to give the AI new orders |
| **RAG** | The AI looks up our own documents first and answers from them, with citations |
| **Vector search (pgvector)** | Searching documents by meaning rather than exact words |
| **Tool calling** | The AI asking the system to run an approved function (for example, "look up this carer") |
| **MCP** | A standard "doorway" for tools; ours is the only path to company systems |
| **Memory** | Short-term context kept for the current task (Redis) |
| **Orchestration** | Deciding the order of steps and who does them (LangGraph) |
| **Router / confidence routing** | Picking the right path for a request and sending unsure cases to a person |
| **Guardrails** | Safety checks on what goes in and comes out |
| **OIDC / Keycloak** | Standard login system |
| **OPA / policy as code** | Permission rules written as testable code and enforced outside the AI |
| **Idempotent** | Safe to repeat; a retry does not cause a second payment or booking |
| **Audit trail** | The complete record of who did what, when, and why |
| **Evals** | Automatic tests that score the AI's quality, run again whenever anything changes |
| **Regression** | Quality getting worse after a change; evals catch it |
| **Observability** | Seeing what the system did, how long it took and what it cost (OpenTelemetry, Langfuse, Grafana) |
| **LiteLLM gateway** | One front door for all model calls: routing, fallbacks, caps |
| **DPIA** | Data protection impact assessment: a document that assesses privacy risk |
| **UK GDPR** | UK data protection law covering personal data |
| **DBS** | Disclosure and Barring Service background check for care workers |
| **Right to work** | Proof that a person may legally work in the UK |
| **SOP** | Standard operating procedure: a written company procedure |
| **ADR** | Architecture decision record: a short note on a decision and why |
| **Legacy system** | The existing company system that we connect to and do not replace |
| **p95** | 95% of requests are at least this fast |
| **k6** | A tool that simulates many users to test speed and capacity |

---

## Appendix B: Capability Coverage Checklist

Shows where each engineering capability area for this platform is covered in this brief and the project.

| Capability area | Where covered |
|---|---|
| Multi-agent platform where agents plan, delegate, use tools and decide, across recruitment, HR, operations and finance | 5.4, 6, FR-13 to FR-17 |
| Reusable patterns and components so other engineers build safely | 5.9, FR-25, 18.3 |
| Evaluation framework: test sets, regression checks | 5.6, FR-24, NFR-5, NFR-6, 18.3 |
| Observability and cost dashboards, internal agent platform | FR-23, NFR-10, NFR-12, 10 |
| Safety framework: audit trails, approval thresholds, escalation rules | 5.5, 5.6, FR-10 to FR-12, FR-18 |
| Model and vendor strategy: model per task, cost control, latency, fallbacks, data residency | 5.7, FR-22, NFR-10, NFR-19 |
| Integration with core business platforms, identity, permissions and data stores | 2, 5.10, FR-1, FR-2, 6 |
| Scale from tens to thousands of users nationally | 14.1, NFR-9 |
| Python and software architecture | 10 (stack), NFR-15, `docs/architecture.md` |
| Production reliability metrics | NFR-6 to NFR-8, 18.5 |
| LangGraph, CrewAI, AutoGen or similar, and judgement on when not to use a framework | 5.8 |
| LLMs, RAG design, tool calling, memory, planning, orchestration | 5.3, 5.4, FR-8, FR-26 |
| Guardrails, prompt-injection defence, access control, human oversight | 5.6, FR-2, FR-11, FR-21, NFR-1 to NFR-4 |
| ERP, HR, CRM or workflow platforms and legacy back ends | 2, 5.10 |
| Mentoring, design review, explaining trade-offs to executives | 5.9, 17.2, this brief's plain-language structure |
| Ownership of outcomes, cost and incidents | 7 (principle 7), 11, 16 |
| Regulated, people-sensitive domain | 1, 11 |
| UK GDPR, DPIA and responsible AI | 11, 18.5 |
| Laravel, PHP and MySQL environments; MCP or similar standards | 2, 5.6, 10 |
| Public talks, writing, open-source work or demos | 18.5 (demo and write-up), 14.8 |
| Redesigning the business process and showing commercial value | 5 (step 0), 14.3 to 14.7 |
| Second-order effects: workload, accountability, compliance, staff trust | 12 |
| Choosing the simple solution, including no AI | 5 (ladder), 5.1, 14.3 note |
| Agents that audit agents, confidence-based routing, self-checking pipelines | 5.4, 5.5, 5.6, FR-15 |
| Challenging the roadmap with evidence and proposing new capabilities | 5.12, 14.7, 19 |
| First six months deliverables | 17.2 |

---

## Appendix C: The Proposed Stack

| Need | Choice | Why (short) |
|---|---|---|
| Agent workflows | **LangGraph** | Clear step-by-step flows that can pause for human approval and resume |
| Simple flows | **Plain Python** | Fixed tasks do not need a framework |
| Fixed checks | **Rules engine** | Exact, explainable answers, no AI guesswork |
| Backend API | **Python 3.12, FastAPI, Pydantic v2** | Fast, typed, easy to test, validates AI output |
| AI models | **LiteLLM gateway** (Claude, OpenAI, a local model) | Right model per task, fallbacks, cost control, data-location choice |
| Knowledge search (RAG) | **PostgreSQL + pgvector** | Agents answer from company policy documents with citations, using one database |
| Memory and queues | **Redis** | Fast short-term memory, caching and job handling |
| Connecting to company systems | **MCP servers** | One controlled, standard doorway to each system |
| Existing company systems | **Laravel 12.72 (PHP 8.4) + MySQL 8.0** (`company-sandbox/`) | Pre-existing HR/ERP/CRM-style API and 21 SOPs handed over running |
| Login and permissions | **Keycloak (OIDC) + OPA (Rego)** | Standard login; rules like "can read but not pay" are checked outside the AI |
| Safety | **Guardrails** (input checks, data masking, output validation) | Protects against prompt injection and data leaks |
| Quality testing | **Promptfoo and/or DeepEval, run in CI** | Automatic tests stop quality from silently getting worse |
| Monitoring and cost | **OpenTelemetry, Langfuse, Grafana** | See what each agent did, how long it took and what it cost |
| Frontend | **React, TypeScript, Tailwind** | Simple, widely known, quick to build |
| Deployment | **Docker Compose first; Kubernetes and Terraform later; k6 load tests** | Reproducible setup, plus proof it scales |
| Compliance documents | **DPIA, UK GDPR mapping, model card** | Shows responsible handling of personal data |

**Not used in this project:** AutoGen and CrewAI (see [ADR-002](adr/ADR-002-orchestration-framework.md)).

---

## Appendix D: Repository Layout and Document Map

This layout matches [`README.md`](../README.md), [`AGENTS.md`](../AGENTS.md) and [`docs/architecture.md`](architecture.md) (section 7):

```
services/agent-api        FastAPI app, OIDC/OPA middleware, router, rules engine, approval API
services/mcp-servers      MCP tool servers (HR, Ops, Finance), the only path to business systems
company-sandbox/          Pre-existing CareOps system (Laravel 12 + MySQL 8 + 21 SOPs, handed over running)
agents/<name>             One folder per helper: supervisor, recruitment, hr, ops, finance, auditor
evals/                    Golden sets, regression checks, ADR-001 benchmark, prompt-injection suite
infra/                    Docker Compose, OPA policies, Keycloak realms, Kubernetes, Terraform, k6
frontend/                 React + TypeScript + Tailwind (three screens only: Requests, Approvals, Dashboard)
docs/                     Project brief, architecture, roadmap, ADRs, compliance (DPIA, model card)
templates/agent-starter   Reusable starter template for building new bounded helpers safely
local/                    Personal notes, learning log & sandbox evaluation guides (gitignored)
```

**How the project documents connect:**

| Document | Purpose |
|---|---|
| [`README.md`](../README.md) | Front-page overview, principles, repository layout and roadmap summary |
| [`AGENTS.md`](../AGENTS.md) | Hard boundaries, coding standards, folder ownership and working rules for engineers and coding agents |
| [`docs/project-brief.md`](project-brief.md) | Business case, legacy system overview, 6-step solution ladder, requirements (`UR`, `FR`, `NFR`), estimates and Definition of Done |
| [`docs/architecture.md`](architecture.md) | High-Level Design (HLD), Low-Level Design (LLD), sequence/state diagrams, 7-layer defence-in-depth and NFR/capacity mapping |
| [`docs/roadmap.md`](roadmap.md) | Phase 0 to Phase 8 vertical-slice delivery plan, open decisions (`O1`–`O8` / `Q-1`–`Q-8`) and blocker register (`B1`–`B13`) |
| [`docs/adr/ADR-001`](adr/ADR-001-router-first-multi-agent-where-justified.md) | 6-step solution ladder: router and deterministic workflows first, multi-agent only where justified |
| [`docs/adr/ADR-002`](adr/ADR-002-orchestration-framework.md) | LangGraph for stateful/pausable flows, plain Python for simple flows, rules engine for exact checks (why CrewAI/AutoGen are not used) |
| [`docs/adr/ADR-003`](adr/ADR-003-permissions-outside-the-model.md) | Permissions enforced outside the model in Keycloak, OPA (`User ∩ Agent`) and MCP servers |
| [`docs/adr/ADR-004`](adr/ADR-004-simulated-existing-company.md) | Integrating with the handed-over CareOps legacy system (`company-sandbox/`) via REST API and RAG only |
