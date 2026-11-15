# Baseline Metrics: Pre-Platform Manual Process Benchmark

- **Status:** Baseline Established (Phase 0)
- **Date:** 2026-11-14
- **Related:** [`docs/project-brief.md`](project-brief.md) (Sections 4.1, 14.2, 14.3, 15, 19 `Q-7`), [`docs/roadmap.md`](roadmap.md) (Task 0.10)

---

## 1. Executive Summary

Before introducing the CareOps Agent Platform, operational tasks in Recruitment, HR, Operations, and Finance are conducted manually across email inboxes, spreadsheets, phone calls, and the pre-existing Laravel administration system (`company-sandbox/`).

To evaluate the platform's return on investment (ROI) and verify compliance with non-functional targets (`NFR-7` latency, `NFR-10` cost reduction), this document establishes the **"before" operational baseline**. These numbers serve as the benchmark against which Phase 8 post-release metrics will be measured.

---

## 2. Baseline Workload & Manual Time per Task

The baseline figures reflect an operational scale of approximately **250 active carers** and **45 care home / domiciliary clients** (the volume mirrored by the synthetic database in `company-sandbox/`).

| Department | Task | Monthly Volume | Manual Handling Time (per item) | Total Manual Hours / Month | High-Friction Operational Cause |
|---|---|---|---|---|---|
| **Recruitment** | CV Screening & Candidate Shortlisting | 400 applications | 10.0 min | **66.7 hrs** | Reading unstructured CVs, cross-checking DBS/right-to-work eligibility against SOP criteria. |
| **HR & Compliance** | Compliance Expiry Tracking & Auditing | Continuous (~300 carers) | Continuous review | **26.0 hrs** | Manual date checks across DBS certificates, training records, and safeguarding certifications. |
| **Operations** | Last-Minute Shift Cover Matching | 150 cover requests | 25.0 min | **62.5 hrs** | Phone calls, manual eligibility filtering, verifying no double-booking or expired DBS. |
| **Finance** | Timesheet Checking & Anomaly Audit | 3,000 timesheets | 3.0 min | **150.0 hrs** | Manual spot-checks for duplicate submissions, hours exceeding shift duration, unapproved carers. |
| **Finance** | Client Invoice Preparation & Cross-Check | 180 invoices | 20.0 min | **60.0 hrs** | Reconciling invoice line items against timesheet totals; correcting invoice mismatch errors. |
| **HR** | Routine Staff Queries & Reminders | 1,500 queries | 4.0 min | **100.0 hrs** | Explaining leave balances, training deadlines, onboarding status via phone/email. |
| **All Teams** | Company Policy & SOP Interpretation | 1,000 questions | 5.0 min | **83.3 hrs** | Searching 21 SOP documents, resolving conflicting annual-leave versions, debating rules. |
| **TOTAL** | | | | **548.5 hrs / mo** | |

*(Note: Section 14.3 of [`docs/project-brief.md`](project-brief.md) conservatively estimates 380 gross hours saved across automated flows after accounting for manual tasks that remain outside system scope).*

---

## 3. Projected Platform Impact (The "After" Target)

| Task | Manual Baseline | Target Time with Platform | Target Realized Savings | Primary Mechanism |
|---|---|---|---|---|
| **CV Screening** | 10 min | 2 min human review | 53.3 hrs/mo (80%) | Step 4 Recruitment Agent pre-ranks CVs with citations; human signs off in Screen 2. |
| **Compliance Expiry** | 26 hrs/mo | 2 hrs/mo exception check | 24.0 hrs/mo (92%) | Step 1 Rules Engine scans 30-day expiry windows deterministically (0 AI cost). |
| **Shift Cover** | 25 min | 8 min human review | 42.5 hrs/mo (68%) | Step 5 Ops Worker ranks eligible, compliant, available carers in seconds. |
| **Timesheets** | 3 min (150 hrs) | 30 hrs (reviews only) | 120.0 hrs/mo (80%) | Step 1 Rules auto-pass 80% of clean timesheets; flags duplicates/anomalies. |
| **Invoice Checks** | 20 min | 6 min | 42.0 hrs/mo (70%) | Step 1 Rules pre-validate invoice line arithmetic against timesheets. |
| **HR Queries** | 4 min | Instant / 1.5 min review | 40.0 hrs/mo (40%) | Step 2 Python state machines handle routine leave workflows & reminders. |
| **Policy Questions** | 5 min | 1.5 min verification | 58.3 hrs/mo (70%) | Step 3 RAG provides exact citations from approved SOPs in < 15 seconds. |
| **NET SAVING** | **548.5 hrs** | | **380.1 gross hrs (228 net hrs @ 60% realization)** | **£3,420 / month saved** (at £15/hr loaded staff cost). |

---

## 4. Empirical Measurement Protocol (Phase 8 Post-Release Verification)

To replace planning assumptions with verified empirical data during Phase 8:
1. **Sample Pilot Week:** During the live pilot, track timestamps for 50 consecutive shift cover requests, 100 CV screenings, and 500 timesheet submissions.
2. **Telemetry Correlation (`NFR-11`, `NFR-12`):**
   - Measure actual time-to-decision using OpenTelemetry trace spans:
     $$\text{Latency} = t_{\text{human\_approval}} - t_{\text{request\_received}}$$
   - Reconstruct manual vs assisted processing times via the audit log (`services/agent-api`).
3. **Escalation & Override Rates (`NFR-6`):**
   - Track percentage of timesheets auto-cleared vs escalated to humans (target: 80% auto-cleared).
   - Track percentage of CV screening recommendations accepted without edit (target: $\ge 85\%$ agreement).
4. **Economic Validation:**
   - Total model inference cost (monitored via LiteLLM and Langfuse, target $< £0.05$ per task) compared against billable staff hours saved.
