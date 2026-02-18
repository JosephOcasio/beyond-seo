#!/usr/bin/env python3
"""Verifier for ECM promotion bundle (request + proof)."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from jsonschema import Draft7Validator

from ecm_reference import evaluate_promotion_bundle, verify_bundle_signatures


def load_json(path: str):
    return json.loads(Path(path).read_text(encoding="utf-8"))


def main() -> int:
    parser = argparse.ArgumentParser(description="Verify ECM promotion proof bundle")
    parser.add_argument("--bundle", required=True, help="Path to ECM bundle JSON")
    parser.add_argument(
        "--schema",
        default="/Users/josephocasio/Documents/New project/aura-governance-hub/schemas/ecm_bundle.schema.json",
        help="Path to ECM bundle schema",
    )
    parser.add_argument("--key-manifest", default="", help="Optional key manifest JSON for signature checks")
    parser.add_argument("--numeric-tolerance", type=float, default=1e-12)
    parser.add_argument("--out", default="", help="Optional output report path")
    args = parser.parse_args()

    bundle = load_json(args.bundle)
    schema = load_json(args.schema)

    schema_errors = [e.message for e in Draft7Validator(schema).iter_errors(bundle)]
    schema_valid = len(schema_errors) == 0

    eval_report = evaluate_promotion_bundle(bundle, numeric_tolerance=args.numeric_tolerance) if schema_valid else {
        "status": "VETO_POLICY",
        "reasons": ["SCHEMA_INVALID"],
    }

    sig_report = None
    if args.key_manifest:
        manifest = load_json(args.key_manifest)
        sig_report = verify_bundle_signatures(bundle, manifest)

    overall_pass = schema_valid and eval_report.get("status") == "PASS"
    if sig_report is not None:
        overall_pass = overall_pass and bool(sig_report.get("all_valid"))

    report = {
        "schema_valid": schema_valid,
        "schema_errors": schema_errors,
        "evaluation": eval_report,
        "signatures": sig_report,
        "overall_pass": overall_pass,
    }

    output = json.dumps(report, indent=2)
    if args.out:
        Path(args.out).write_text(output + "\n", encoding="utf-8")
    print(output)

    return 0 if overall_pass else 1


if __name__ == "__main__":
    raise SystemExit(main())
