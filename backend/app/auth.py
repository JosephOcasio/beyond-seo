"""JWT auth + RBAC helpers for reasoner endpoints."""

from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Iterable

import jwt
from flask import Request


class AuthError(Exception):
    """Raised for authentication/authorization failures."""

    def __init__(self, message: str, status_code: int) -> None:
        super().__init__(message)
        self.status_code = status_code


@dataclass(frozen=True)
class AuthContext:
    subject: str
    email: str
    roles: set[str]
    claims: dict


def _env_flag(name: str, default: bool) -> bool:
    raw = os.environ.get(name, "true" if default else "false").strip().lower()
    return raw in {"1", "true", "yes", "on"}


def _extract_bearer_token(req: Request) -> str:
    header = (req.headers.get("Authorization") or "").strip()
    if not header.startswith("Bearer "):
        raise AuthError("Missing or invalid Authorization header", 401)
    token = header.removeprefix("Bearer ").strip()
    if not token:
        raise AuthError("Missing bearer token", 401)
    return token


def _decode_jwt(token: str) -> dict:
    secret = os.environ.get("JWT_SECRET", "").strip()
    if not secret:
        raise AuthError("Server misconfigured: JWT_SECRET is not set", 500)
    algorithm = os.environ.get("JWT_ALGORITHM", "HS256")
    audience = os.environ.get("JWT_AUDIENCE", "").strip() or None
    issuer = os.environ.get("JWT_ISSUER", "").strip() or None

    options = {"require": ["exp", "iat"]}
    kwargs = {
        "algorithms": [algorithm],
        "options": options,
    }
    if audience:
        kwargs["audience"] = audience
    if issuer:
        kwargs["issuer"] = issuer

    try:
        return jwt.decode(token, secret, **kwargs)
    except jwt.PyJWTError as exc:
        raise AuthError(f"Invalid token: {exc}", 401) from exc


def get_auth_context(req: Request) -> AuthContext:
    if not _env_flag("RBAC_ENFORCE", True):
        return AuthContext(
            subject="rbac-disabled",
            email="",
            roles={"admin", "auditor", "operator", "viewer", "signer"},
            claims={},
        )

    token = _extract_bearer_token(req)
    claims = _decode_jwt(token)
    subject = str(claims.get("sub") or claims.get("user_id") or "")
    email = str(claims.get("email") or "")
    role_claim = claims.get("roles", claims.get("role", []))
    if isinstance(role_claim, str):
        roles = {role_claim}
    elif isinstance(role_claim, list):
        roles = {str(v) for v in role_claim}
    else:
        roles = set()

    if not subject and not email:
        raise AuthError("Token missing subject", 401)

    return AuthContext(subject=subject, email=email, roles=roles, claims=claims)


def require_roles(ctx: AuthContext, allowed_roles: Iterable[str]) -> None:
    allowed = {r.strip() for r in allowed_roles}
    if not (ctx.roles & allowed):
        raise AuthError("Insufficient role for this action", 403)

