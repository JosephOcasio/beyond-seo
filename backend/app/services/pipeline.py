"""Deterministic admissibility pipeline implementation."""

from __future__ import annotations

import hashlib
import json
import re
import uuid
from datetime import datetime, timezone
from typing import Any

from sqlalchemy import desc, select
from sqlalchemy.orm import Session

from backend.app.models import Claim, ConstraintScore, LedgerEvent
from backend.app.schemas import (
    ClaimReportResponse,
    EvaluateClaimRequest,
    EvaluateClaimResponse,
    GovernanceVerdict,
    LedgerEventOut,
    Measurement,
)

PIPELINE_VERSION = "v1"
FORMULA_PATTERNS = [
    r"\$\$[^$]+\$\$",
    r"\$[^$]+\$",
    r"\\\([^\)]+\\\)",
    r"\\\[[^\]]+\\\]",
    r"[A-Za-z0-9_]+\s*=\s*[^\r\n]{2,}",
]


def _model_dump(model: object) -> dict[str, Any]:
    if hasattr(model, "model_dump"):
        return getattr(model, "model_dump")()
    return getattr(model, "dict")()


def _utc_now() -> datetime:
    return datetime.now(timezone.utc)


def _hash_text(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def _extract_formulas(text: str) -> list[str]:
    seen: set[str] = set()
    ordered: list[str] = []
    for pattern in FORMULA_PATTERNS:
        for match in re.finditer(pattern, text, flags=re.MULTILINE):
            item = match.group(0).strip()
            if not item:
                continue
            if item in seen:
                continue
            seen.add(item)
            ordered.append(item)
    return ordered


def _compute_measurement(text: str) -> Measurement:
    words = len(re.findall(r"\S+", text))
    characters = len(text)
    lines = len(text.splitlines()) if text else 0
    formulas = _extract_formulas(text)
    formula_count = len(formulas)
    formula_density = round(formula_count / max(words, 1), 6)
    digit_count = sum(ch.isdigit() for ch in text)
    digit_ratio = round(digit_count / max(characters, 1), 6)

    return Measurement(
        words=words,
        characters=characters,
        lines=lines,
        formula_count=formula_count,
        formula_density=formula_density,
        digit_ratio=digit_ratio,
        formulas=formulas,
    )


def _compute_admissibility(measurement: Measurement) -> float:
    length_signal = min(measurement.words / 300.0, 1.0)
    formula_signal = min(measurement.formula_count / 12.0, 1.0)
    numeric_signal = min(measurement.digit_ratio / 0.12, 1.0)
    score = (0.45 * length_signal) + (0.40 * formula_signal) + (0.15 * numeric_signal)
    return round(score, 6)


def _compute_drift(session: Session, source_name: str, new_score: float) -> float:
    stmt = (
        select(ConstraintScore.admissibility_score)
        .join(Claim, Claim.id == ConstraintScore.claim_id)
        .where(Claim.source_name == source_name)
        .order_by(desc(ConstraintScore.id))
        .limit(1)
    )
    previous = session.scalar(stmt)
    if previous is None:
        return 0.0
    return round(abs(float(previous) - new_score), 6)


def _governance_verdict(
    *,
    measurement: Measurement,
    admissibility_score: float,
    drift_score: float,
    min_admissibility: float,
    drift_tolerance: float,
) -> GovernanceVerdict:
    if measurement.words < 20:
        return GovernanceVerdict(status="HOLD", reason="insufficient_text")
    if admissibility_score < min_admissibility:
        return GovernanceVerdict(status="VETO", reason="admissibility_below_threshold")
    if drift_score > drift_tolerance:
        return GovernanceVerdict(status="HOLD", reason="drift_above_tolerance")
    return GovernanceVerdict(status="PASS", reason="meets_thresholds")


def _append_ledger(
    session: Session,
    claim_id: int,
    stage: str,
    payload: dict[str, Any],
    event_type: str = "decision",
) -> LedgerEvent:
    event = LedgerEvent(
        claim_id=claim_id,
        stage=stage,
        event_type=event_type,
        payload_json=json.dumps(payload, ensure_ascii=True, sort_keys=True),
        created_at=_utc_now(),
    )
    session.add(event)
    return event


def run_pipeline(session: Session, req: EvaluateClaimRequest, text: str) -> EvaluateClaimResponse:
    measurement = _compute_measurement(text)
    admissibility_score = _compute_admissibility(measurement)
    drift_score = _compute_drift(session, req.source_name, admissibility_score)
    governance = _governance_verdict(
        measurement=measurement,
        admissibility_score=admissibility_score,
        drift_score=drift_score,
        min_admissibility=req.min_admissibility,
        drift_tolerance=req.drift_tolerance,
    )

    claim = Claim(
        external_id=uuid.uuid4().hex,
        source_name=req.source_name,
        source_type=req.source_type,
        source_path=(req.file_path or "").strip(),
        content_hash=_hash_text(text),
        content_text=text,
        metadata_json=json.dumps(req.metadata, ensure_ascii=True, sort_keys=True),
        created_at=_utc_now(),
    )
    session.add(claim)
    session.flush()

    _append_ledger(
        session,
        claim.id,
        "input",
        {
            "source_name": req.source_name,
            "source_type": req.source_type,
            "source_path": req.file_path or "",
            "pipeline_version": PIPELINE_VERSION,
        },
    )
    _append_ledger(session, claim.id, "measurement", _model_dump(measurement))
    _append_ledger(session, claim.id, "admissibility", {"score": admissibility_score})
    _append_ledger(
        session,
        claim.id,
        "drift_audit",
        {"drift_score": drift_score, "drift_tolerance": req.drift_tolerance},
    )
    _append_ledger(
        session,
        claim.id,
        "governance",
        {
            "status": governance.status,
            "reason": governance.reason,
            "min_admissibility": req.min_admissibility,
        },
    )

    score = ConstraintScore(
        claim_id=claim.id,
        measurement_json=json.dumps(_model_dump(measurement), ensure_ascii=True, sort_keys=True),
        admissibility_score=admissibility_score,
        drift_score=drift_score,
        governance_status=governance.status,
        governance_reason=governance.reason,
        pipeline_version=PIPELINE_VERSION,
        created_at=_utc_now(),
    )
    session.add(score)
    session.flush()

    _append_ledger(
        session,
        claim.id,
        "ledger",
        {"claim_id": claim.external_id, "score_row_id": score.id},
        event_type="append",
    )

    return EvaluateClaimResponse(
        claim_id=claim.external_id,
        source_name=claim.source_name,
        source_type=claim.source_type,
        created_at=claim.created_at,
        measurement=measurement,
        admissibility_score=admissibility_score,
        drift_score=drift_score,
        governance=governance,
        pipeline_version=PIPELINE_VERSION,
    )


def _model_validate_measurement(payload: dict[str, Any]) -> Measurement:
    if hasattr(Measurement, "model_validate"):
        return Measurement.model_validate(payload)
    return Measurement.parse_obj(payload)


def get_claim_report(session: Session, claim_external_id: str) -> ClaimReportResponse | None:
    claim_stmt = select(Claim).where(Claim.external_id == claim_external_id).limit(1)
    claim = session.scalar(claim_stmt)
    if claim is None:
        return None

    score_stmt = (
        select(ConstraintScore)
        .where(ConstraintScore.claim_id == claim.id)
        .order_by(desc(ConstraintScore.id))
        .limit(1)
    )
    score = session.scalar(score_stmt)
    if score is None:
        return None

    ledger_stmt = select(LedgerEvent).where(LedgerEvent.claim_id == claim.id).order_by(LedgerEvent.id.asc())
    ledger_rows = list(session.scalars(ledger_stmt))

    measurement = _model_validate_measurement(json.loads(score.measurement_json))
    governance = GovernanceVerdict(status=score.governance_status, reason=score.governance_reason)

    ledger = [
        LedgerEventOut(
            id=row.id,
            stage=row.stage,
            event_type=row.event_type,
            payload=json.loads(row.payload_json),
            created_at=row.created_at,
        )
        for row in ledger_rows
    ]

    return ClaimReportResponse(
        claim_id=claim.external_id,
        source_name=claim.source_name,
        source_type=claim.source_type,
        created_at=claim.created_at,
        metadata=json.loads(claim.metadata_json),
        measurement=measurement,
        admissibility_score=score.admissibility_score,
        drift_score=score.drift_score,
        governance=governance,
        pipeline_version=score.pipeline_version,
        ledger=ledger,
    )
