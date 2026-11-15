"""Domain models for CareOps MCP Tools (Task 1.5)."""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field


class CarerDbsInput(BaseModel):
    """Input payload for get_carer_dbs_status tool."""

    model_config = ConfigDict(extra="forbid")

    carer_id: int = Field(gt=0, description="Unique integer ID of the carer")


class CarerDbsResult(BaseModel):
    """Output payload for get_carer_dbs_status tool."""

    model_config = ConfigDict(extra="ignore")

    carer_id: int
    status: Literal["valid", "expiring_soon", "expired", "missing"]
    issued_on: str | None = None
    expires_on: str | None = None
    days_remaining: int | None = None
    verification_source: str = "company-sandbox-legacy"
