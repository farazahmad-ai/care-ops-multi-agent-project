"""Environment-driven configuration using Pydantic Settings."""

from functools import lru_cache
from typing import Literal

from pydantic import AnyHttpUrl, Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class PlatformSettings(BaseSettings):
    """Base platform settings loaded from environment variables (never hard-coded)."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    app_env: Literal["local", "staging", "production"] = "local"
    log_level: Literal["DEBUG", "INFO", "WARNING", "ERROR"] = "INFO"
    service_name: str = "careops-agent-api"

    # Required from environment so URLs, timeouts, and cost caps are never hard-coded.
    careops_legacy_url: AnyHttpUrl
    request_timeout_seconds: float = Field(..., gt=0)
    agent_run_budget_gbp: float = Field(..., gt=0)


@lru_cache(maxsize=1)
def get_settings() -> PlatformSettings:
    """Return cached platform settings loaded from the active environment."""
    return PlatformSettings()
