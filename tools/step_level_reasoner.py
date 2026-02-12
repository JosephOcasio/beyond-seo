#!/usr/bin/env python3
"""
Step-level verification pulse for the local federal audit workflow.

This script is evidence-first:
- Reads current matrix/manifest artifacts.
- Computes temporal + forensic + institutional checks.
- Runs a Jacobian-style rank check (local proxy if no external gate is provided).
- Emits PASS / HOLD / VETO with machine-readable diagnostics.
- Optionally appends a hash-sealed entry to a local ledger.
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import hashlib
import json
import subprocess
import sys
from pathlib import Path
from typing import Dict, Optional, Tuple

import numpy as np


DEFAULT_ZERO_POINT = "2026-03-04T17:00:00+00:00"


def now_utc() -> dt.datetime:
    return dt.datetime.now(dt.timezone.utc)


def parse_iso(value: str) -> dt.datetime:
    parsed = dt.datetime.fromisoformat(value)
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=dt.timezone.utc)
    return parsed.astimezone(dt.timezone.utc)


def load_json(path: Path) -> Optional[dict]:
    if not path.exists():
        return None
    return json.loads(path.read_text(encoding="utf-8"))


def load_matrix_counts(path: Path) -> Dict[str, int]:
    counts: Dict[str, int] = {}
    if not path.exists():
        return counts
    with path.open("r", encoding="utf-8", newline="") as f:
        for row in csv.DictReader(f):
            s = (row.get("verification_status") or "UNKNOWN").strip()
            counts[s] = counts.get(s, 0) + 1
    return counts


def external_jacobian_gate(cmd: str) -> Optional[Dict[str, object]]:
    """Run external Jacobian command and parse the last JSON object from stdout."""
    proc = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if proc.returncode != 0:
        return {
            "mode": "external",
            "error": proc.stderr.strip() or f"exit {proc.returncode}",
            "rank_pass": False,
        }
    out = proc.stdout.strip()
    start = out.rfind("{")
    if start == -1:
        return {"mode": "external", "error": "no JSON in output", "rank_pass": False}
    try:
        obj = json.loads(out[start:])
    except Exception:
        return {"mode": "external", "error": "invalid JSON payload", "rank_pass": False}

    rank = obj.get("rank")
    expected = obj.get("expected")
    rank_pass = isinstance(rank, int) and isinstance(expected, int) and rank >= expected
    return {
        "mode": "external",
        "rank": rank,
        "expected": expected,
        "rank_pass": bool(rank_pass),
        "raw": obj,
    }


def proxy_jacobian_gate(verified: int, provisional: int, temporal_score: float, forensic_score: float) -> Dict[str, object]:
    """
    Local Jacobian proxy:
    Matrix built from current pipeline state; rank < expected means underdetermined.
    """
    J = np.array(
        [
            [float(max(verified, 0)), float(max(provisional, 0))],
            [float(temporal_score), float(forensic_score)],
        ],
        dtype=float,
    )
    rank = int(np.linalg.matrix_rank(J))
    expected = J.shape[1]
    singular_values = [float(v) for v in np.linalg.svd(J, compute_uv=False)]
    return {
        "mode": "proxy",
        "rank": rank,
        "expected": expected,
        "singular_values": singular_values,
        "rank_pass": rank >= expected,
    }


def compute_scores(
    zero_point: dt.datetime,
    verified: int,
    provisional: int,
    institutional_operational: bool,
) -> Tuple[dict, dict, dict, float]:
    days_left = (zero_point - now_utc()).total_seconds() / 86400.0
    # Temporal score: 1.0 inside 30-day window before zero point, else 0.5.
    temporal_score = 1.0 if 0 <= days_left <= 30 else 0.5
    temporal = {"days_to_zero_point": days_left, "score": temporal_score}

    total = verified + provisional
    forensic_score = (verified / total) if total > 0 else 0.0
    forensic = {"verified_rows": verified, "provisional_rows": provisional, "score": forensic_score}

    institutional_score = 1.0 if institutional_operational else 0.0
    institutional = {"operational": institutional_operational, "score": institutional_score}

    propulsion = (0.2 * temporal_score + 0.6 * forensic_score + 0.2 * institutional_score) * 100.0
    return temporal, forensic, institutional, propulsion


def build_outcome(
    rank_pass: bool,
    provisional_rows: int,
    forensic_score: float,
    propulsion: float,
    min_propulsion: float,
) -> Tuple[str, list]:
    reasons = []
    if not rank_pass:
        reasons.append("JACOBIAN_RANK_DEFICIENT")
        return "VETO", reasons
    if provisional_rows > 0:
        reasons.append("PROVISIONAL_EVIDENCE_REMAINING")
    if forensic_score < 0.5:
        reasons.append("LOW_FORENSIC_VERIFIED_RATIO")
    if propulsion < min_propulsion:
        reasons.append("PROPULSION_BELOW_THRESHOLD")

    return ("PASS" if not reasons else "HOLD"), reasons


def append_seal(ledger_path: Path, diagnostics: dict, signer: str) -> dict:
    payload = {
        "event": "SEAL",
        "timestamp_utc": now_utc().isoformat(),
        "signer": signer,
        "diagnostics": diagnostics,
    }
    canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"))
    digest = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
    entry = {"payload": payload, "payload_sha256": digest}
    ledger_path.parent.mkdir(parents=True, exist_ok=True)
    with ledger_path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(entry) + "\n")
    return entry


def main() -> int:
    parser = argparse.ArgumentParser(description="Run step-level verification pulse.")
    parser.add_argument(
        "--matrix",
        default="/Users/josephocasio/Documents/New project/out/us_audit/final_gap_closed_matrix.csv",
        help="Path to final gap-closed matrix CSV.",
    )
    parser.add_argument(
        "--manifest",
        default="/Users/josephocasio/Documents/New project/out/us_audit/manual_vault/manifest.json",
        help="Path to source manifest JSON.",
    )
    parser.add_argument(
        "--zero-point",
        default=DEFAULT_ZERO_POINT,
        help="Zero-point ISO timestamp (UTC).",
    )
    parser.add_argument(
        "--institutional-operational",
        action="store_true",
        help="Set institutional pillar to operational.",
    )
    parser.add_argument(
        "--jacobian-cmd",
        default="",
        help="Optional external Jacobian command. If omitted, proxy gate is used.",
    )
    parser.add_argument(
        "--min-propulsion",
        type=float,
        default=60.0,
        help="Minimum propulsion threshold for PASS/HOLD evaluation.",
    )
    parser.add_argument("--out-dir", default="/Users/josephocasio/Documents/New project/out/us_audit/reasoner")
    parser.add_argument("--sign", action="store_true", help="Append hash-sealed PASS entry to ledger.")
    parser.add_argument("--signer", default="", help="Signer id for seal entry.")
    parser.add_argument(
        "--ledger",
        default="/Users/josephocasio/Documents/New project/out/us_audit/reasoner/step_level_ledger.jsonl",
        help="Local ledger path.",
    )
    args = parser.parse_args()

    matrix_path = Path(args.matrix)
    manifest_path = Path(args.manifest)
    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    counts = load_matrix_counts(matrix_path)
    verified = counts.get("VERIFIED_LOCAL_PRIMARY", 0)
    provisional = counts.get("PROVISIONAL_NEEDS_PRIMARY_DOC", 0)

    zero_point = parse_iso(args.zero_point)
    temporal, forensic, institutional, propulsion = compute_scores(
        zero_point=zero_point,
        verified=verified,
        provisional=provisional,
        institutional_operational=args.institutional_operational,
    )

    if args.jacobian_cmd.strip():
        jacobian = external_jacobian_gate(args.jacobian_cmd.strip())
    else:
        jacobian = proxy_jacobian_gate(
            verified=verified,
            provisional=provisional,
            temporal_score=temporal["score"],
            forensic_score=forensic["score"],
        )

    manifest = load_json(manifest_path) or {"entries": []}
    manifest_entries = len(manifest.get("entries", []))

    outcome, reasons = build_outcome(
        rank_pass=bool(jacobian.get("rank_pass")),
        provisional_rows=provisional,
        forensic_score=forensic["score"],
        propulsion=propulsion,
        min_propulsion=args.min_propulsion,
    )

    diagnostics = {
        "generated_at_utc": now_utc().isoformat(),
        "inputs": {
            "matrix": str(matrix_path),
            "manifest": str(manifest_path),
            "zero_point_utc": zero_point.isoformat(),
            "institutional_operational": args.institutional_operational,
            "min_propulsion": args.min_propulsion,
        },
        "mechanism_path": {
            "temporal": temporal,
            "forensic": forensic,
            "institutional": institutional,
            "jacobian": jacobian,
            "manifest_entries": manifest_entries,
            "propulsion_percent": propulsion,
        },
        "outcome": outcome,
        "reasons": reasons,
    }

    ts = now_utc().strftime("%Y%m%dT%H%M%SZ")
    out_json = out_dir / f"step_reasoner_{ts}.json"
    out_json.write_text(json.dumps(diagnostics, indent=2), encoding="utf-8")
    print(f"Diagnostics: {out_json}")
    print(f"Outcome: {outcome}")

    if args.sign:
        if outcome != "PASS":
            print("Not signing: outcome is not PASS.")
            return 2 if outcome == "VETO" else 1
        if not args.signer.strip():
            print("Not signing: --signer is required when --sign is used.")
            return 4
        entry = append_seal(Path(args.ledger), diagnostics, signer=args.signer.strip())
        print(f"Ledger entry appended: {args.ledger}")
        print(f"Payload hash: {entry['payload_sha256']}")

    if outcome == "PASS":
        return 0
    if outcome == "VETO":
        return 2
    return 1


if __name__ == "__main__":
    sys.exit(main())
