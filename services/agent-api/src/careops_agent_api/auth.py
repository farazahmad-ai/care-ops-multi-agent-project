"""OIDC JWT Authentication and JWKS Verification via Keycloak (Task 1.7, ADR-007)."""

from __future__ import annotations

import logging
import os
from typing import Any

import httpx
import jwt
from fastapi import HTTPException, Security, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jwt.algorithms import RSAAlgorithm
from pydantic import BaseModel, ConfigDict

logger = logging.getLogger(__name__)

security_scheme = HTTPBearer(auto_error=False)

DEFAULT_KEYCLOAK_URL = "http://localhost:8081"
DEFAULT_REALM = "careops"


class AuthenticatedUser(BaseModel):
    """User identity and roles extracted from verified Keycloak JWT."""

    model_config = ConfigDict(extra="ignore")

    user_id: str
    username: str
    email: str
    roles: list[str]

    @property
    def primary_role(self) -> str:
        """Returns the highest priority role."""
        for role in ["department_head", "team_lead", "staff"]:
            if role in self.roles:
                return role
        return self.roles[0] if self.roles else "none"


class KeycloakTokenVerifier:
    """Fetches Keycloak JWKS and validates incoming RS256 JWT access tokens."""

    def __init__(self, keycloak_url: str | None = None, realm: str | None = None) -> None:
        self.keycloak_url = (
            keycloak_url or os.environ.get("KEYCLOAK_URL", DEFAULT_KEYCLOAK_URL)
        ).rstrip("/")
        self.realm = realm or os.environ.get("KEYCLOAK_REALM", DEFAULT_REALM)
        self.jwks_url = f"{self.keycloak_url}/realms/{self.realm}/protocol/openid-connect/certs"
        self._cached_keys: dict[str, Any] = {}

    async def get_public_key(self, kid: str) -> Any:
        """Retrieves and caches public RSA keys from Keycloak JWKS endpoint."""
        if kid in self._cached_keys:
            return self._cached_keys[kid]

        async with httpx.AsyncClient(timeout=10.0) as client:
            try:
                resp = await client.get(self.jwks_url)
                resp.raise_for_status()
                jwks = resp.json()
            except Exception as exc:
                logger.error("Failed to fetch Keycloak JWKS", exc_info=exc)
                raise HTTPException(
                    status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                    detail="Authentication service temporarily unavailable",
                ) from exc

        for key_dict in jwks.get("keys", []):
            if key_dict.get("kid") == kid:
                public_key = RSAAlgorithm.from_jwk(key_dict)
                self._cached_keys[kid] = public_key
                return public_key

        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Signing key '{kid}' not found in Keycloak JWKS",
        )

    async def verify_token(self, token: str) -> AuthenticatedUser:
        """Validates token signature, expiration, and extracts claims."""
        try:
            unverified_headers = jwt.get_unverified_header(token)
            kid = unverified_headers.get("kid")
            if not kid:
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Missing 'kid' in token header",
                )

            public_key = await self.get_public_key(kid)
            expected_issuer = f"{self.keycloak_url}/realms/{self.realm}"

            payload = jwt.decode(
                token,
                key=public_key,
                algorithms=["RS256"],
                issuer=expected_issuer,
                options={
                    "verify_aud": False
                },  # Audience verification disabled for public SPA tokens
            )

            realm_access = payload.get("realm_access", {})
            roles: list[str] = realm_access.get("roles", [])

            return AuthenticatedUser(
                user_id=payload.get("sub", ""),
                username=payload.get("preferred_username", ""),
                email=payload.get("email", ""),
                roles=roles,
            )
        except jwt.ExpiredSignatureError as exc:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Token has expired",
            ) from exc
        except jwt.PyJWTError as exc:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail=f"Invalid token: {exc}",
            ) from exc


token_verifier = KeycloakTokenVerifier()


async def get_current_user(
    credentials: HTTPAuthorizationCredentials | None = Security(security_scheme),
) -> AuthenticatedUser:
    """FastAPI dependency: extracts and validates Keycloak Bearer token."""
    if credentials is None or not credentials.credentials:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization bearer token",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return await token_verifier.verify_token(credentials.credentials)
