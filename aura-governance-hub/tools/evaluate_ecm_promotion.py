#!/usr/bin/env python3
"""Deterministic evaluator for ECM existence-class promotion.

Computes Delta-F and applies monotonic + evidence + constraint checks.
"""

from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, List


CLASS_ORDER = [
    "E0_SPECULATIVE",
    "E1_STRUCTURAL",
    "E2_INFORMATIONAL",
    "E3_CAUSAL",
    "E4_HISTORICAL",
    "E5_PHYSICAL",
]


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def load_json(path: str) -> Dict[str, Any]:
    return json.loads(Path(path).read_text(encoding="utf-8"))


def idx(name: str) -> int:
    if name not in CLASS_ORDER:
        raise ValueError(f"Unknown existence class: {name}")
    return CLASS_ORDER.index(name)


def evaluate(req: Dict[str, Any], policy: Dict[str, Any]) -> Dict[str, Any]:
    current_class = req["current_class"]
    target_class = req["target_class"]
    transition = f"{current_class}->{target_class}"
    reasons: List[str] = []

    current_i = idx(current_class)
    target_i = idx(target_class)

    # Policy: monotonic, one step
    if policy.get("monotonic_required", True) and target_i < current_i:
        return _result(req, transition, 0.0, 0.0, 0, 0, [], [], "VETO_POLICY", ["NON_MONOTONIC_BACKWARD"])

    if policy.get("allow_one_step_only", True) and target_i != current_i + 1:
        return _result(req, transition, 0.0, 0.0, 0, 0, [], [], "VETO_POLICY", ["NON_MONOTONIC_SKIP"])

    prior = float(req["prior_feasible_volume"])
    posterior = float(req["posterior_feasible_volume"])
    if prior <= 0.0 or posterior <= 0.0:
        return _result(req, transition, 0.0, 0.0, 0, 0, [], [], "VETO_POLICY", ["INVALID_FEASIBLE_VOLUME"])

    delta_f = 1.0 - (posterior / prior)

    delta_req = float(policy.get("delta_f_required_by_transition", {}).get(transition, 0.0))
    evidence_count = len(req.get("evidence", []))
    evidence_req = int(policy.get("min_evidence_count_by_transition", {}).get(transition, 1))
    constraints_required = list(policy.get("required_constraints_by_target_class", {}).get(target_class, []))
    constraints_verified = set(req.get("constraints_verified", []))
    constraints_missing = [c for c in constraints_required if c not in constraints_verified]

    if constraints_missing:
        return _result(
            req,
            transition,
            delta_f,
            delta_req,
            evidence_count,
            evidence_req,
            constraints_required,
            constraints_missing,
            "VETO_POLICY",
            ["MISSING_REQUIRED_CONSTRAINTS"],
        )

    if evidence_count < evidence_req:
        reasons.append("INSUFFICIENT_EVIDENCE_COUNT")

    if delta_f < delta_req:
        reasons.append("DELTA_F_BELOW_THRESHOLD")

    if reasons:
        return _result(
            req,
            transition,
            delta_f,
            delta_req,
            evidence_count,
            evidence_req,
            constraints_required,
            constraints_missing,
            "REFUSE_UNCERTAIN",
            reasons,
        )

    return _result(
        req,
        transition,
        delta_f,
        delta_req,
        evidence_count,
        evidence_req,
        constraints_required,
        constraints_missing,
        "PASS",
        ["OK"],
    )


def _result(
    req: Dict[str, Any],
    transition: str,
    delta_f: float,
    delta_req: float,
    evidence_count: int,
    evidence_req: int,
    constraints_required: List[str],
    constraints_missing: List[str],
    decision: str,
    reason_codes: List[str],
) -> Dict[str, Any]:
    return {
        "request_id": req["request_id"],
        "claim_id": req["claim_id"],
        "evaluated_at_utc": utc_now_iso(),
        "transition": transition,
        "delta_f": round(delta_f, 8),
        "delta_f_formula": "delta_f = 1 - (posterior_feasible_volume / prior_feasible_volume)",
        "delta_f_required": delta_req,
        "evidence_count": evidence_count,
        "required_evidence_count": evidence_req,
        "constraints_required": constraints_required,
        "constraints_missing": constraints_missing,
        "decision": decision,
        "reason_codes": reason_codes,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Evaluate deterministic ECM promotion")
    parser.add_argument("--request", required=True, help="Path to ecm_promotion_request JSON")
    parser.add_argument(
        "--policy",
        default="/Users/josephocasio/Documents/New project/aura-governance-hub/docs/ecm_promotion_policy.template.json",
        help="Path to ECM policy JSON",
    )
    parser.add_argument("--out", default="", help="Optional output path for JSON result")
    args = parser.parse_args()

    req = load_json(args.request)
    policy = load_json(args.policy)
    result = evaluate(req=req, policy=policy)

    text = json.dumps(result, indent=2)
    if args.out:
        Path(args.out).write_text(text + "\n", encoding="utf-8")
    print(text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

