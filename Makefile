# CareOps Agent Platform Makefile (Phase 0)
.PHONY: lint test eval up down

lint:
	uv run ruff check services agents
	uv run black --check services agents
	uv run mypy --strict services/agent-api/src services/agent-api/tests

test:
	uv run pytest

# Stub: Phase 3 will add Promptfoo / DeepEval golden evaluation suite
eval:
	@echo "[STUB] make eval is not implemented yet — scheduled for Phase 3 (Task 3.12)."

# Stub: Phase 1 will add platform docker-compose.yml (PostgreSQL+pgvector, Redis, Keycloak, OPA)
up:
	@echo "[STUB] make up is not implemented yet — platform Compose starts in Phase 1 (Task 1.1). Legacy sandbox lives in company-sandbox/."

# Stub: Phase 1 will tear down platform docker-compose.yml
down:
	@echo "[STUB] make down is not implemented yet — platform Compose starts in Phase 1 (Task 1.1)."

