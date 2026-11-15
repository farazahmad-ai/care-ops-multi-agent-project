# Development Environment Baseline

Recorded for **Phase 0 (Task 0.2)** on Windows (`win32`, PowerShell).

## Toolchain Check

| Tool | Required | Found | Status | Notes |
|---|---|---|---|---|
| **Python** | 3.12 (`AGENTS.md`) | `3.12.13` via `uv` (`3.13.5` system) | Ready | Pinned to `3.12` via `.python-version` and `requires-python = ">=3.12,<3.13"` (`O1`; pending `ADR-005`). |
| **uv** | Package/venv manager (`Task 0.6`) | `0.12.6` | Ready | Pending formal adoption in `ADR-005` (`O2`). |
| **Docker** | Local platform containers | `29.9.2` | Ready | Verified running (`careops_legacy_db`, `careops_legacy_app`, `careops_legacy_web`). |
| **Docker Compose** | `v2+` | `v5.6.1` | Ready | Verified with `company-sandbox/docker-compose.yml`. |
| **Node.js / npm** | Frontend (`React + TS`) | `v24.17.0` / `11.13.0` | Ready | Needed for `frontend/` in Phase 1. |
| **GNU Make** | `Makefile` runner (`Task 0.7`) | Not found on `PATH` | Missing | Available via `winget install ezwinports.make` (GNU Make 4.4.1). |
| **Git** | Version control (`Task 0.1`) | Deferred (`O3`) | Deferred | Repo not yet initialized (`git init` deferred per owner). |

## Handed-Over Legacy Baseline (`company-sandbox/`)

- **Health Endpoint:** `GET http://localhost:8080/api/health`
- **Verified Response (`2026-11-12`):** `{"status":"ok","service":"careops-legacy","database":"connected","timestamp":"2026-11-12T14:01:36+00:00"}`
- **Network:** `careops_sandbox_network` (`careops_legacy_web` on `:8080`, `careops_legacy_db` on `:3307`).

