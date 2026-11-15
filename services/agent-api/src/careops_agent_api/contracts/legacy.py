"""Pydantic contract models for CareOps Legacy Backend API (company-sandbox).

Matches the specification defined in company-sandbox/laravel-app/openapi.yaml.
"""

from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field


class HealthResponse(BaseModel):
    """Schema for GET /api/health."""

    model_config = ConfigDict(extra="forbid")

    status: Literal["ok"]
    service: str
    database: Literal["connected"]
    timestamp: str


class ComplianceCheckItem(BaseModel):
    """Schema for individual compliance check item from GET /api/carers/{id}/compliance."""

    model_config = ConfigDict(extra="ignore")

    id: int
    type: Literal["dbs", "right_to_work", "moving_handling", "safeguarding"]
    issued_on: str
    expires_on: str
    stored_status: str
    computed_status: str
    days_remaining: int


class ErrorDetail(BaseModel):
    """Schema for standard error payload."""

    model_config = ConfigDict(extra="ignore")

    code: str
    message: str
    details: list[Any] | dict[str, Any] = Field(default_factory=list)


class LegacyErrorResponse(BaseModel):
    """Schema for 4xx/5xx error responses from legacy CareOps API."""

    model_config = ConfigDict(extra="ignore")

    error: ErrorDetail
