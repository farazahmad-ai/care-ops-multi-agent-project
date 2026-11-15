"""Unit tests for environment-driven PlatformSettings."""

from pathlib import Path

import pytest
from careops_agent_api.config import PlatformSettings
from pydantic import ValidationError


def test_settings_load_from_environment(monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
    """Settings should load valid values from environment variables."""
    monkeypatch.chdir(tmp_path)
    monkeypatch.setenv("APP_ENV", "local")
    monkeypatch.setenv("LOG_LEVEL", "DEBUG")
    monkeypatch.setenv("SERVICE_NAME", "careops-test-api")
    monkeypatch.setenv("CAREOPS_LEGACY_URL", "http://localhost:8080")
    monkeypatch.setenv("REQUEST_TIMEOUT_SECONDS", "15")
    monkeypatch.setenv("AGENT_RUN_BUDGET_GBP", "0.50")

    settings = PlatformSettings()

    assert settings.app_env == "local"
    assert settings.log_level == "DEBUG"
    assert settings.service_name == "careops-test-api"
    assert str(settings.careops_legacy_url) == "http://localhost:8080/"
    assert settings.request_timeout_seconds == 15.0
    assert settings.agent_run_budget_gbp == 0.50


def test_settings_reject_missing_required_fields(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    """Required environment settings must fail validation when absent."""
    monkeypatch.chdir(tmp_path)
    monkeypatch.delenv("CAREOPS_LEGACY_URL", raising=False)
    monkeypatch.delenv("REQUEST_TIMEOUT_SECONDS", raising=False)
    monkeypatch.delenv("AGENT_RUN_BUDGET_GBP", raising=False)

    with pytest.raises(ValidationError):
        PlatformSettings()


def test_settings_reject_non_positive_budget(
    monkeypatch: pytest.MonkeyPatch, tmp_path: Path
) -> None:
    """Budget and timeout thresholds must be strictly positive."""
    monkeypatch.chdir(tmp_path)
    monkeypatch.setenv("CAREOPS_LEGACY_URL", "http://localhost:8080")
    monkeypatch.setenv("REQUEST_TIMEOUT_SECONDS", "10")
    monkeypatch.setenv("AGENT_RUN_BUDGET_GBP", "0")

    with pytest.raises(ValidationError):
        PlatformSettings()
