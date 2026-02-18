#!/usr/bin/env python3
"""Deterministic checker for lucidity legal-constraint packet completeness.

Input packet format (JSON):
{
  "artifacts": {"AUP": "/path/or-id", ...},
  "dates": {
    "solicitation_template_date": "YYYY-MM-DD",
    "policy_effective_date": "YYYY-MM-DD",
    "sunset_review_date": "YYYY-MM-DD"
  },
  "flags": {
    "reporting_workflow_active": true
  }
}
"""

from __future__ import annotations

import argparse
import json
from datetime import date
from pathlib import Path
from typing import Any, Dict


def parse_date(value: str | None) -> date | None:
    if not value:
        return None
    return date.fromisoformat(value)


def load_json(path: Path) -> Dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def check_constraint(packet: Dict[str, Any], constraint: Dict[str, Any], hard_dates: Dict[str, str]) -> Dict[str, Any]:
    artifacts = packet.get("artifacts", {})
    dates = packet.get("dates", {})
    flags = packet.get("flags", {})

    missing = [k for k in constraint.get("expected_artifacts", []) if not artifacts.get(k)]
    passed = len(missing) == 0
    reasons = []

    if missing:
        reasons.append(f"missing_artifacts:{','.join(missing)}")

    cid = constraint["id"]

    if cid == "C3_NEW_SOLICITATION_CLAUSES":
        issued = parse_date(hard_dates.get("memo_issue_date"))
        templ = parse_date(dates.get("solicitation_template_date"))
        if issued and templ and templ < issued:
            passed = False
            reasons.append("solicitation_template_date_before_memo_issue")
        elif templ is None:
            passed = False
            reasons.append("missing_date:solicitation_template_date")

    if cid == "C5_POLICY_AND_USER_REPORTING_DEADLINE":
        deadline = parse_date(hard_dates.get("agency_update_deadline"))
        effective = parse_date(dates.get("policy_effective_date"))
        if deadline and effective and effective > deadline:
            passed = False
            reasons.append("policy_effective_date_after_deadline")
        elif effective is None:
            passed = False
            reasons.append("missing_date:policy_effective_date")
        if not bool(flags.get("reporting_workflow_active", False)):
            passed = False
            reasons.append("reporting_workflow_inactive")

    if cid == "C9_SUNSET_CONTROL":
        sunset = parse_date(hard_dates.get("memo_sunset_date"))
        review = parse_date(dates.get("sunset_review_date"))
        if review is None:
            passed = False
            reasons.append("missing_date:sunset_review_date")
        elif sunset and review > sunset:
            passed = False
            reasons.append("sunset_review_date_after_sunset")

    return {
        "id": cid,
        "severity": constraint.get("severity", "required"),
        "passed": passed,
        "reasons": reasons or ["ok"],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Check procurement packet against constraints_atomic_v1.json")
    parser.add_argument("--packet", required=True, help="Path to packet JSON")
    parser.add_argument(
        "--constraints",
        default="/Users/josephocasio/Documents/New project/aura-governance-hub/docs/constraints_atomic_v1.json",
        help="Path to constraints JSON",
    )
    parser.add_argument("--out", default="", help="Optional output report JSON path")
    args = parser.parse_args()

    packet = load_json(Path(args.packet))
    spec = load_json(Path(args.constraints))

    hard_dates = {
        "memo_issue_date": spec.get("hard_dates", {}).get("memo_issue_date"),
        "agency_update_deadline": spec.get("hard_dates", {}).get("agency_update_deadline"),
        "memo_sunset_date": spec.get("hard_dates", {}).get("memo_sunset_date"),
    }

    results = [check_constraint(packet, c, hard_dates) for c in spec.get("constraints", [])]

    required = [r for r in results if r["severity"] == "required"]
    required_pass = all(r["passed"] for r in required)

    report = {
        "spec_id": spec.get("spec_id"),
        "required_pass": required_pass,
        "counts": {
            "total": len(results),
            "passed": sum(1 for r in results if r["passed"]),
            "failed": sum(1 for r in results if not r["passed"]),
            "required_failed": sum(1 for r in required if not r["passed"]),
        },
        "results": results,
    }

    text = json.dumps(report, indent=2)
    if args.out:
        Path(args.out).write_text(text + "\n", encoding="utf-8")
    print(text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
