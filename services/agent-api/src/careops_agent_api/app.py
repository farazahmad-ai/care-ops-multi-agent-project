"""FastAPI application entrypoint for CareOps Agent Platform (Task 1.7, FR-1, FR-3)."""

from __future__ import annotations

import logging
import uuid

import httpx
from careops_mcp.server import (
    CareOpsMcpServer,
    McpPolicyDeniedError,
    McpRecordNotFoundError,
)
from fastapi import Depends, FastAPI, HTTPException, Request, Response, status
from fastapi.middleware.cors import CORSMiddleware
from starlette.middleware.base import BaseHTTPMiddleware, RequestResponseEndpoint

from careops_agent_api.auth import AuthenticatedUser, get_current_user
from careops_agent_api.logging import get_trace_id, set_trace_id
from careops_agent_api.schemas import HealthCheckResponse, RequestPayload, RequestResponse

logger = logging.getLogger(__name__)


class TraceIdMiddleware(BaseHTTPMiddleware):
    """Propagates distributed trace IDs across HTTP boundaries (NFR-12)."""

    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        trace_id = (
            request.headers.get("X-Trace-ID")
            or request.headers.get("traceparent")
            or f"trace-{uuid.uuid4().hex[:16]}"
        )
        set_trace_id(trace_id)
        response = await call_next(request)
        response.headers["X-Trace-ID"] = trace_id
        return response


def create_app() -> FastAPI:
    """Builds and configures the FastAPI application instance."""
    app = FastAPI(
        title="CareOps Agent Platform API",
        description="Core orchestration API and Safe Gateway for CareOps Ltd (Phase 1)",
        version="0.1.0",
    )

    # 1. Trace ID Middleware (Task 1.8, NFR-12)
    app.add_middleware(TraceIdMiddleware)

    # 2. CORS Middleware for React SPA Frontend (ADR-007, NFR-18)
    app.add_middleware(
        CORSMiddleware,
        allow_origins=[
            "http://localhost:3000",
            "http://127.0.0.1:3000",
            "http://localhost:5173",
            "http://127.0.0.1:5173",
        ],
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
        expose_headers=["X-Trace-ID"],
    )

    # 3. Global MCP Server Safe Gateway instance
    mcp_server = CareOpsMcpServer()

    @app.get("/health", response_model=HealthCheckResponse, tags=["Health"])
    async def health_check() -> HealthCheckResponse:
        """Liveness probe checking platform infrastructure services."""
        services_status: dict[str, str] = {}
        async with httpx.AsyncClient(timeout=3.0) as client:
            # Check OPA
            try:
                opa_resp = await client.get(f"{mcp_server.opa_url}/health")
                services_status["opa"] = "ok" if opa_resp.status_code == 200 else "error"
            except Exception:
                services_status["opa"] = "unreachable"

            # Check Legacy API
            try:
                legacy_resp = await client.get(f"{mcp_server.legacy_url}/api/health")
                services_status["legacy_backend"] = (
                    "ok" if legacy_resp.status_code == 200 else "error"
                )
            except Exception:
                services_status["legacy_backend"] = "unreachable"

        overall = "healthy" if all(v == "ok" for v in services_status.values()) else "degraded"
        return HealthCheckResponse(
            status=overall,
            services=services_status,
        )

    @app.post("/api/v1/requests", response_model=RequestResponse, tags=["Requests"])
    async def handle_request(
        payload: RequestPayload,
        user: AuthenticatedUser = Depends(get_current_user),
    ) -> RequestResponse:
        """Processes user operational requests through Safe Gateway (Screen 1, FR-3)."""
        current_trace = get_trace_id() or f"trace-{uuid.uuid4().hex[:16]}"
        logger.info(
            "Received request",
            extra={
                "intent": payload.intent,
                "user_id": user.user_id,
                "role": user.primary_role,
                "trace_id": current_trace,
            },
        )

        if payload.intent in {"check_carer_dbs", "get_carer_compliance"}:
            carer_id = payload.parameters.get("carer_id")
            if not isinstance(carer_id, int) or carer_id <= 0:
                raise HTTPException(
                    status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                    detail="Parameter 'carer_id' must be a positive integer",
                )

            try:
                result = await mcp_server.get_carer_dbs_status(
                    carer_id=carer_id,
                    user_id=user.email or user.username,
                    user_role=user.primary_role,
                    trace_id=current_trace,
                )
                return RequestResponse(
                    trace_id=current_trace,
                    status="done",
                    result=result.model_dump(),
                )
            except McpPolicyDeniedError as exc:
                logger.warning("Access denied by OPA policy", extra={"trace_id": current_trace})
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail=str(exc),
                ) from exc
            except McpRecordNotFoundError as exc:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail=str(exc),
                ) from exc

        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Intent '{payload.intent}' is not recognized",
        )

    return app


app = create_app()
