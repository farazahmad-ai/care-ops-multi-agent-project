"""Tests for Platform Infrastructure & Network Topology (Phase 1, Task 1.1).

Verifies Docker Compose specifications, network boundaries, and volume configurations
as required by ADR-006 and NFR-1.
"""

from pathlib import Path


def test_platform_compose_file_exists() -> None:
    """Verifies that the root docker-compose.yml exists."""
    compose_path = Path("docker-compose.yml")
    assert compose_path.is_file(), "Root docker-compose.yml must exist"
    content = compose_path.read_text(encoding="utf-8")

    # Required platform services
    assert "careops_platform_postgres" in content
    assert "careops_platform_redis" in content
    assert "careops_platform_opa" in content
    assert "careops_platform_keycloak" in content

    # Required network topology (ADR-006)
    assert "careops_platform_network" in content
    assert "careops_sandbox_network" in content
    assert "external: true" in content

    # Port isolation: Keycloak on 8081 to avoid conflict with legacy web 8080
    assert '"8081:8081"' in content or "'8081:8081'" in content


def test_postgres_init_script() -> None:
    """Verifies that PostgreSQL init script enables pgvector and defines audit table."""
    init_sql = Path("infra/postgres/init.sql")
    assert init_sql.is_file(), "infra/postgres/init.sql must exist"
    sql = init_sql.read_text(encoding="utf-8")
    assert "CREATE EXTENSION IF NOT EXISTS vector;" in sql
    assert "CREATE TABLE IF NOT EXISTS audit_logs" in sql


def test_opa_policy_directory() -> None:
    """Verifies that OPA policies directory and base system policy exist."""
    policy_file = Path("infra/opa/policies/system.rego")
    assert policy_file.is_file(), "infra/opa/policies/system.rego must exist"
    assert "package system" in policy_file.read_text(encoding="utf-8")
