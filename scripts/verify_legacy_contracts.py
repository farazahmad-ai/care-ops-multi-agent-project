#!/usr/bin/env python3
"""Integration check script to verify handed-over CareOps endpoints for Phase 1 (Task 1.2).

Validates that GET /api/health and GET /api/carers/{id}/compliance match the OpenAPI
contract specification in company-sandbox/laravel-app/openapi.yaml.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

# Ensure services/agent-api/src is on sys.path for contract imports
SRC_PATH = Path(__file__).resolve().parent.parent / "services" / "agent-api" / "src"
if str(SRC_PATH) not in sys.path:
    sys.path.insert(0, str(SRC_PATH))

from careops_agent_api.contracts.legacy import (  # noqa: E402
    ComplianceCheckItem,
    HealthResponse,
    LegacyErrorResponse,
)
from pydantic import ValidationError  # noqa: E402

DEFAULT_BASE_URL = "http://localhost:8080"
DEFAULT_TOKEN = "careops_legacy_sandbox_token_2026"


def request_json(
    url: str,
    token: str | None = None,
) -> tuple[int, Any]:
    """Sends an HTTP GET request and returns (status_code, parsed_json)."""
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"

    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            status_code = int(response.status)
            body = response.read().decode("utf-8")
            return status_code, json.loads(body)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8")
        try:
            payload = json.loads(body)
        except Exception:
            payload = {"raw": body}
        return int(exc.code), payload
    except urllib.error.URLError as exc:
        print(f"[-] Network connection error connecting to {url}: {exc.reason}", file=sys.stderr)
        sys.exit(1)


def verify_contracts(base_url: str, token: str) -> bool:
    """Verifies all required endpoints against OpenAPI contracts."""
    print(f"[*] Starting CareOps Legacy Contract Verification against: {base_url}")
    all_passed = True

    # 1. Verify GET /api/health (unauthenticated)
    health_url = f"{base_url}/api/health"
    print(f"[*] Checking GET {health_url} ... ", end="")
    code, data = request_json(health_url)
    if code != 200:
        print(f"FAILED (expected 200, got {code})")
        all_passed = False
    else:
        try:
            health = HealthResponse.model_validate(data)
            print(f"OK (status={health.status}, db={health.database})")
        except ValidationError as err:
            print(f"FAILED (schema mismatch: {err})")
            all_passed = False

    # 2. Verify GET /api/carers/1/compliance without token (should be 401)
    unauth_url = f"{base_url}/api/carers/1/compliance"
    print(f"[*] Checking 401 on GET {unauth_url} without token ... ", end="")
    code, data = request_json(unauth_url)
    if code != 401:
        print(f"FAILED (expected 401, got {code})")
        all_passed = False
    else:
        try:
            unauth_err = LegacyErrorResponse.model_validate(data)
            print(f"OK (code={unauth_err.error.code})")
        except ValidationError as v_err:
            print(f"FAILED (error schema mismatch: {v_err})")
            all_passed = False

    # 3. Verify GET /api/carers/1/compliance with valid service token
    auth_url = f"{base_url}/api/carers/1/compliance"
    print(f"[*] Checking authenticated GET {auth_url} ... ", end="")
    code, data = request_json(auth_url, token=token)
    if code != 200:
        print(f"FAILED (expected 200, got {code})")
        all_passed = False
    elif not isinstance(data, list):
        print(f"FAILED (expected list of checks, got {type(data)})")
        all_passed = False
    else:
        try:
            checks = [ComplianceCheckItem.model_validate(item) for item in data]
            dbs_checks = [c for c in checks if c.type == "dbs"]
            if not dbs_checks:
                print("FAILED (no 'dbs' check found in carer compliance)")
                all_passed = False
            else:
                dbs = dbs_checks[0]
                print(
                    f"OK ({len(checks)} checks validated, DBS status={dbs.computed_status}, "
                    f"days_remaining={dbs.days_remaining})"
                )
        except ValidationError as v_err:
            print(f"FAILED (compliance item schema mismatch: {v_err})")
            all_passed = False

    # 4. Verify GET /api/carers/999999/compliance (not found)
    missing_url = f"{base_url}/api/carers/999999/compliance"
    print(f"[*] Checking 404 on GET {missing_url} ... ", end="")
    code, data = request_json(missing_url, token=token)
    if code != 404:
        print(f"FAILED (expected 404, got {code})")
        all_passed = False
    else:
        try:
            not_found_err = LegacyErrorResponse.model_validate(data)
            print(f"OK (code={not_found_err.error.code})")
        except ValidationError as v_err:
            print(f"FAILED (404 schema mismatch: {v_err})")
            all_passed = False

    return all_passed


def main() -> None:
    parser = argparse.ArgumentParser(description="Verify legacy CareOps API contracts.")
    parser.add_argument(
        "--base-url",
        default=os.environ.get("CAREOPS_LEGACY_URL", DEFAULT_BASE_URL),
        help=f"Base URL of legacy CareOps backend (default: {DEFAULT_BASE_URL})",
    )
    parser.add_argument(
        "--token",
        default=os.environ.get("CAREOPS_SERVICE_TOKEN", DEFAULT_TOKEN),
        help="Service bearer token for authenticated endpoints",
    )
    args = parser.parse_args()

    success = verify_contracts(args.base_url, args.token)
    if success:
        print("\n[+] All contract checks PASSED. Legacy API matches openapi.yaml.")
        sys.exit(0)
    else:
        print("\n[-] Contract verification FAILED.", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
