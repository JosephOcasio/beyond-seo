#!/usr/bin/env python3
"""
Compute ingestion coverage percentages and write a gap-closed matrix snapshot.
"""

from __future__ import annotations

import argparse
import csv
import glob
import json
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import List


ROOT = Path("/Users/josephocasio/Documents/New project")
OUT_DIR = ROOT / "out" / "us_audit" / "coverage"


@dataclass
class Target:
    id: str
    description: str
    required_patterns: List[str]


TARGETS = [
    Target(
        id="gao_core_pdfs_or_indexes",
        description="Core GAO package (high-risk + DOD + IRS pages).",
        required_patterns=[
            "inbox/us_sources/GAO-25-107743*",
            "inbox/us_sources/GAO-25-108191*",
            "inbox/us_sources/GAO-25-107427*",
            "inbox/us_sources/GAO-25-108052*",
            "inbox/us_sources/GAO-25-107375*",
        ],
    ),
    Target(
        id="gao_recommendations_csv",
        description="GAO recommendation export CSVs for aging analysis.",
        required_patterns=["inbox/us_sources/*gao*recommend*.csv", "inbox/us_sources/*recommend*.csv"],
    ),
    Target(
        id="federal_ingest_artifacts",
        description="Treasury + USAspending JSON artifacts.",
        required_patterns=[
            "out/us_audit/federal_ingest/treasury_debt_to_penny_*.json",
            "out/us_audit/federal_ingest/usaspending_awards_department_of_defense_*.json",
            "out/us_audit/federal_ingest/usaspending_transactions_department_of_defense_*.json",
            "out/us_audit/federal_ingest/usaspending_awards_internal_revenue_service_*.json",
            "out/us_audit/federal_ingest/usaspending_transactions_internal_revenue_service_*.json",
            "out/us_audit/federal_ingest/usaspending_awards_social_security_administration_*.json",
            "out/us_audit/federal_ingest/usaspending_transactions_social_security_administration_*.json",
        ],
    ),
    Target(
        id="derived_outputs",
        description="Derived analytics outputs present.",
        required_patterns=[
            "out/us_audit/gao_recommendations_aging/gao_recommendations_aging.csv",
            "out/us_audit/priority_saturation/priority_saturation.csv",
            "out/us_audit/dod_irs_all/irs_control_group.json",
            "out/us_audit/final_gap_closed_matrix.csv",
        ],
    ),
]


def resolve(pattern: str) -> list[str]:
    return sorted(glob.glob(str(ROOT / pattern)))


def calc_target_coverage(target: Target) -> dict:
    checks = []
    matched = 0
    for p in target.required_patterns:
        files = resolve(p)
        ok = len(files) > 0
        if ok:
            matched += 1
        checks.append({"pattern": p, "matched": ok, "files": files[:10]})
    pct = (matched / len(target.required_patterns) * 100.0) if target.required_patterns else 100.0
    return {
        "id": target.id,
        "description": target.description,
        "required_count": len(target.required_patterns),
        "matched_count": matched,
        "coverage_pct": round(pct, 2),
        "checks": checks,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Build ingestion coverage report.")
    parser.parse_args()

    rows = [calc_target_coverage(t) for t in TARGETS]
    overall_required = sum(r["required_count"] for r in rows)
    overall_matched = sum(r["matched_count"] for r in rows)
    overall_pct = (overall_matched / overall_required * 100.0) if overall_required else 100.0

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    out_json = OUT_DIR / "coverage_report.json"
    out_csv = OUT_DIR / "coverage_report.csv"
    out_md = OUT_DIR / "coverage_report.md"

    payload = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "overall_required": overall_required,
        "overall_matched": overall_matched,
        "overall_coverage_pct": round(overall_pct, 2),
        "targets": rows,
    }
    out_json.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    with out_csv.open("w", encoding="utf-8", newline="") as f:
        w = csv.writer(f)
        w.writerow(["target_id", "required_count", "matched_count", "coverage_pct", "description"])
        for r in rows:
            w.writerow([r["id"], r["required_count"], r["matched_count"], r["coverage_pct"], r["description"]])

    lines = [
        "# Coverage Report",
        "",
        f"Generated: {payload['generated_at_utc']}",
        f"- Overall coverage: **{payload['overall_coverage_pct']}%** ({overall_matched}/{overall_required})",
        "",
        "## Target Coverage",
    ]
    for r in rows:
        lines.append(
            f"- **{r['id']}**: {r['coverage_pct']}% ({r['matched_count']}/{r['required_count']}) — {r['description']}"
        )
    lines.append("")
    lines.append("## Open Gaps")
    gaps = 0
    for r in rows:
        for c in r["checks"]:
            if not c["matched"]:
                gaps += 1
                lines.append(f"- {r['id']}: missing `{c['pattern']}`")
    if gaps == 0:
        lines.append("- None")
    out_md.write_text("\n".join(lines) + "\n", encoding="utf-8")

    print(f"overall_coverage_pct: {payload['overall_coverage_pct']}")
    print(f"wrote: {out_json}")
    print(f"wrote: {out_csv}")
    print(f"wrote: {out_md}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
