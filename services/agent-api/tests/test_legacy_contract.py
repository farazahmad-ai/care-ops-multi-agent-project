"""Automated contract tests for CareOps Legacy API (Task 1.2, FR-4).

Verifies that the running company-sandbox endpoints match openapi.yaml specifications.
"""

import json
import os
import urllib.error
import urllib.request

import pytest
from careops_agent_api.contracts.legacy import (
    ComplianceCheckItem,
    HealthResponse,
    LegacyErrorResponse,
)

BASE_URL = os.environ.get("CAREOPS_LEGACY_URL", "http://localhost:8080")
SERVICE_TOKEN = os.environ.get("CAREOPS_SERVICE_TOKEN", "careops_legacy_sandbox_token_2026")


def _is_sandbox_reachable() -> bool:
    try:
        req = urllib.request.Request(
            f"{BASE_URL}/api/health", headers={"Accept": "application/json"}
        )
        with urllib.request.urlopen(req, timeout=10) as resp:
            return bool(resp.status == 200)
    except Exception:
        return False


SANDBOX_AVAILABLE = _is_sandbox_reachable()


@pytest.mark.skipif(not SANDBOX_AVAILABLE, reason="company-sandbox legacy backend is not running")
def test_legacy_health_contract() -> None:
    """Verifies GET /api/health contract."""
    with urllib.request.urlopen(f"{BASE_URL}/api/health", timeout=10) as resp:
        assert resp.status == 200
        import json

        data = json.loads(resp.read().decode())
        health = HealthResponse.model_validate(data)
        assert health.status == "ok"
        assert health.database == "connected"


@pytest.mark.skipif(not SANDBOX_AVAILABLE, reason="company-sandbox legacy backend is not running")
def test_legacy_compliance_unauthenticated() -> None:
    """Verifies that GET /api/carers/{id}/compliance without token returns 401."""
    req = urllib.request.Request(f"{BASE_URL}/api/carers/1/compliance")
    with pytest.raises(urllib.error.HTTPError) as exc_info:
        urllib.request.urlopen(req, timeout=10)

    assert exc_info.value.code == 401

    body = json.loads(exc_info.value.read().decode())
    err = LegacyErrorResponse.model_validate(body)
    assert err.error.code == "UNAUTHENTICATED"


@pytest.mark.skipif(not SANDBOX_AVAILABLE, reason="company-sandbox legacy backend is not running")
def test_legacy_compliance_authenticated_contract() -> None:
    """Verifies GET /api/carers/{id}/compliance schema with valid token."""
    headers = {"Authorization": f"Bearer {SERVICE_TOKEN}"}
    req = urllib.request.Request(f"{BASE_URL}/api/carers/1/compliance", headers=headers)
    with urllib.request.urlopen(req, timeout=10) as resp:
        assert resp.status == 200
        import json

        data = json.loads(resp.read().decode())
        assert isinstance(data, list)
        checks = [ComplianceCheckItem.model_validate(item) for item in data]
        types = {c.type for c in checks}
        assert "dbs" in types, "Carer 1 must have a DBS check"
        for check in checks:
            assert check.computed_status in {"valid", "expiring_soon", "expired"}
            assert check.days_remaining is not None
