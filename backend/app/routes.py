"""HTTP routes for triggering and polling step-level reasoner jobs."""

from __future__ import annotations

from celery.result import AsyncResult
from flask import Blueprint, jsonify, request

from backend.app.auth import AuthError, get_auth_context, require_roles
from backend.celery_app import celery
from backend.tasks import run_step_level_reasoner


reasoner_api = Blueprint("reasoner_api", __name__)


@reasoner_api.post("/api/reasoner/run")
def reasoner_run():
    try:
        auth_ctx = get_auth_context(request)
        require_roles(auth_ctx, {"admin", "auditor", "operator"})
    except AuthError as exc:
        return jsonify({"error": str(exc)}), exc.status_code

    body = request.get_json(silent=True) or {}
    sign_requested = bool(body.get("sign", False))
    if sign_requested:
        try:
            require_roles(auth_ctx, {"admin", "signer"})
        except AuthError as exc:
            return jsonify({"error": str(exc)}), exc.status_code

        requested_signer = str(body.get("signer", "")).strip()
        if requested_signer and "admin" not in auth_ctx.roles:
            identity_candidates = {auth_ctx.subject, auth_ctx.email}
            if requested_signer not in identity_candidates:
                return jsonify({"error": "signer must match token identity unless role=admin"}), 403

    task_kwargs = {
        "zero_point": body.get("zero_point", "2026-03-04T17:00:00+00:00"),
        "institutional_operational": bool(body.get("institutional_operational", False)),
        "jacobian_cmd": body.get("jacobian_cmd", ""),
        "min_propulsion": float(body.get("min_propulsion", 60.0)),
        "sign": sign_requested,
        "signer": body.get("signer", ""),
        "requested_by": auth_ctx.subject or auth_ctx.email,
    }

    for optional in ("matrix", "manifest", "out_dir", "ledger"):
        value = body.get(optional)
        if value:
            task_kwargs[optional] = value

    try:
        task = run_step_level_reasoner.apply_async(kwargs=task_kwargs)
    except (TypeError, ValueError) as exc:
        return jsonify({"error": str(exc)}), 400

    return jsonify({"task_id": task.id}), 202


@reasoner_api.get("/api/reasoner/jobs/<task_id>")
def reasoner_job_status(task_id: str):
    try:
        auth_ctx = get_auth_context(request)
        require_roles(auth_ctx, {"admin", "auditor", "operator", "viewer", "signer"})
    except AuthError as exc:
        return jsonify({"error": str(exc)}), exc.status_code

    result = AsyncResult(task_id, app=celery)
    data = {
        "task_id": task_id,
        "state": result.state,
        "ready": result.ready(),
        "requested_by": auth_ctx.subject or auth_ctx.email,
    }
    if result.ready():
        if result.successful():
            data["result"] = result.result
        else:
            data["error"] = str(result.result)
    else:
        data["meta"] = result.info
    return jsonify(data), 200
