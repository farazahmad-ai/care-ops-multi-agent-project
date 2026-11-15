# CareOps Legacy Company Systems (`company-sandbox/`)

Pre-existing CareOps Ltd backend systems (Laravel 12.72 on PHP 8.4 + MySQL 8.0 + nginx 1.27) and company Standard Operating Procedures (`sops/`).

## Layout

| Folder / File | Contents |
|---|---|
| `docker-compose.yml` | Container stack: `app` (PHP 8.4-FPM), `web` (nginx on `:8080`), `db` (MySQL 8.0 on `:3307`) |
| `Makefile` | Lifecycle commands: `up`, `down`, `logs`, `reset`, `seed`, `test`, `shell` |
| `.env.example` | Environment template (copy to `.env` before starting) |
| `database/schema.md` | 13-table relational schema and Mermaid ER diagram |
| `laravel-app/` | Laravel 12 REST API (`openapi.yaml`), service rules (`app/Services/`), and feature tests |
| `sops/` | 21 company SOP markdown documents across HR, Recruitment, Operations, Finance, and General |

## Service Endpoints & Auth

- **Base URL:** `http://localhost:8080/api`
- **Healthcheck (unauthenticated):** `GET /api/health`
- **Authentication:** All business routes require `Authorization: Bearer <SERVICE_TOKEN>` configured via `.env` (fails closed if unset).
- **API Specification:** [`laravel-app/openapi.yaml`](laravel-app/openapi.yaml)
- **Database Schema:** [`database/schema.md`](database/schema.md)

## Quick Start

```bash
make up     # start db, app, web containers
make test   # run full PHPUnit suite against isolated careops_legacy_test DB
```

```powershell
# Healthcheck
Invoke-RestMethod -Uri "http://localhost:8080/api/health"
```

## Business Rule Test Coverage (`CareOpsApiRulesTest` & `CareOpsApiEndpointsTest`)

| Rule | Description | Covered By Test(s) |
|---|---|---|
| Auth | Missing, invalid, or unconfigured `SERVICE_TOKEN` -> `401 UNAUTHENTICATED` | `test_auth_missing_token_returns_401`, `test_auth_wrong_token_returns_401`, `test_unconfigured_service_token_fails_closed` |
| Validation | Request validation errors -> `422 VALIDATION_FAILED` | `test_validation_error_uses_standard_422_format` |
| R1 | Carer DBS/RTW missing or expired on shift date -> `422 CARER_COMPLIANCE_EXPIRED` | `test_r1_cannot_assign_carer_with_expired_compliance` |
| R2 | Overlapping shift assignment -> `409 SHIFT_OVERLAP` | `test_r2_cannot_assign_carer_to_overlapping_shifts` |
| R3 | Carer on approved leave -> `409 CARER_ON_LEAVE` | `test_r3_cannot_assign_carer_on_approved_leave` |
| R4 | Shift not `open` (`409 SHIFT_NOT_OPEN`) or role mismatch (`422 ROLE_MISMATCH`); senior covers carer + idempotent retry | `test_r4_cannot_assign_carer_to_non_open_shift_or_role_mismatch`, `test_operations_clients_shifts_eligible_idempotent_assign_and_cancel` |
| R5 & R6 | Double approval (`409 TIMESHEET_ALREADY_APPROVED`) and editing/rejecting locked timesheet (`409 TIMESHEET_LOCKED`) | `test_r5_and_r6_timesheet_approval_and_locking`, `test_finance_pay_rates_timesheets_reject_and_invoicing_r11` |
| R7 | Leave end < start (`422 INVALID_DATES`) and overlapping approved leave (`409 LEAVE_OVERLAP`) | `test_r7_leave_request_dates_validation`, `test_carers_endpoints_filters_compliance_and_leave_workflow` |
| R8 | Candidate hire requires `offered` status + valid DBS & RTW (`422 CANDIDATE_NOT_HIREABLE`) | `test_r8_candidate_hire_rule` |
| R9 & R10 | Hours `<= 0` (`422 INVALID_HOURS`), duplicate timesheet (`409 DUPLICATE_TIMESHEET`), > shift + 1h (`422 HOURS_EXCEED_SHIFT`) | `test_r9_and_r10_timesheet_hours_and_duplicate` |
| R11 | Invoicing aggregates approved uninvoiced timesheets using role rates; empty -> `422 NOTHING_TO_INVOICE` | `test_finance_pay_rates_timesheets_reject_and_invoicing_r11` |
| R12 | Candidate forward lifecycle transitions only (`422 INVALID_TRANSITION`) | `test_r12_candidate_status_transition` |
