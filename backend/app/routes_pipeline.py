"""API routes for ingest + deterministic admissibility pipeline."""

from __future__ import annotations

import json

from flask import Blueprint, jsonify, request
from pydantic import ValidationError

from backend.app.auth import AuthError, get_auth_context, require_roles
from backend.app.db import get_session
from backend.app.schemas import EvaluateClaimRequest
from backend.app.services.ocr_client import OCRClientError, extract_text
from backend.app.services.pipeline import get_claim_report, run_pipeline

prototype_api = Blueprint("prototype_api", __name__)


def _model_dump(model: object) -> dict:
    if hasattr(model, "model_dump"):
        return getattr(model, "model_dump")()
    return getattr(model, "dict")()


def _json_safe_errors(exc: ValidationError) -> list[dict]:
    raw = exc.errors()
    # Pydantic error context may include non-JSON values (e.g., ValueError instances).
    return json.loads(json.dumps(raw, default=str))


@prototype_api.get("/api/prototype/health")
def prototype_health() -> tuple[dict, int]:
    return {"status": "ok", "service": "prototype_pipeline"}, 200


@prototype_api.post("/api/prototype/claims/evaluate")
def evaluate_claim():
    try:
        auth_ctx = get_auth_context(request)
        require_roles(auth_ctx, {"admin", "auditor", "operator"})
    except AuthError as exc:
        return jsonify({"error": str(exc)}), exc.status_code

    body = request.get_json(silent=True) or {}
    try:
        req = EvaluateClaimRequest.model_validate(body)
    except ValidationError as exc:
        return jsonify({"error": "validation_error", "details": _json_safe_errors(exc)}), 400

    try:
        text = (req.raw_text or "").strip()
        if req.source_type in {"image", "pdf"}:
            text = extract_text(req.file_path or "")

        with get_session() as session:
            result = run_pipeline(session, req, text)
            return jsonify(_model_dump(result)), 201
    except OCRClientError as exc:
        return jsonify({"error": str(exc)}), 422
    except ValueError as exc:
        return jsonify({"error": str(exc)}), 400


@prototype_api.get("/api/prototype/claims/<claim_id>")
def get_claim(claim_id: str):
    try:
        auth_ctx = get_auth_context(request)
        require_roles(auth_ctx, {"admin", "auditor", "operator", "viewer", "signer"})
    except AuthError as exc:
        return jsonify({"error": str(exc)}), exc.status_code

    with get_session() as session:
        report = get_claim_report(session, claim_id)
        if report is None:
            return jsonify({"error": "not_found"}), 404
        payload = _model_dump(report)
        payload["ledger"] = [_model_dump(item) for item in report.ledger]
        return jsonify(payload), 200


@prototype_api.get("/api/prototype/reports/<claim_id>")
def get_report(claim_id: str):
    return get_claim(claim_id)
