#!/usr/bin/env python3
"""
Compute ingestion coverage percentages and write a gap-closed matrix snapshot.
"""

from __future__ import annotations

import argparse
import csv
import glob
import json
import re
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import List, Tuple


ROOT = Path("/Users/josephocasio/Documents/New project")
OUT_DIR = ROOT / "out" / "us_audit" / "coverage"
STUB_TOKENS = (
    "access denied",
    "you don't have permission to access",
    "errors.edgesuite.net",
)
MIN_BYTES_DEFAULT = 512
MIN_BYTES_BY_SUFFIX = {
    ".pdf": 10 * 1024,
    ".csv": 128,
    ".json": 256,
    ".html": 10 * 1024,
    ".htm": 10 * 1024,
}


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


def _read_head(path: Path, byte_count: int = 8192) -> bytes:
    with path.open("rb") as f:
        return f.read(byte_count)


def validate_file_integrity(path_str: str) -> Tuple[bool, str]:
    path = Path(path_str)
    suffix = path.suffix.lower()
    min_bytes = MIN_BYTES_BY_SUFFIX.get(suffix, MIN_BYTES_DEFAULT)

    try:
        size = path.stat().st_size
    except OSError as exc:
        return False, f"stat_failed:{exc}"

    if size < min_bytes:
        return False, f"file_too_small:{size}<{min_bytes}"

    try:
        head = _read_head(path)
    except OSError as exc:
        return False, f"read_failed:{exc}"

    head_text = head.decode("utf-8", errors="ignore").lower()
    if any(token in head_text for token in STUB_TOKENS):
        return False, "access_denied_stub"

    if suffix == ".pdf":
        if not head.startswith(b"%PDF-"):
            return False, "pdf_header_missing"
        return True, "verified_pdf"

    if suffix == ".csv":
        if head_text.lstrip().startswith("<html") or "<title>access denied</title>" in head_text:
            return False, "csv_is_html_stub"
        if "," not in head_text and "\t" not in head_text:
            return False, "csv_delimiter_missing"
        return True, "verified_csv"

    if suffix == ".json":
        try:
            json.loads(path.read_text(encoding="utf-8"))
        except Exception as exc:
            return False, f"json_parse_failed:{exc}"
        return True, "verified_json"

    if suffix in (".html", ".htm"):
        if re.search(r"<title>\s*access denied\s*</title>", head_text):
            return False, "html_access_denied_stub"
        return True, "verified_html"

    return True, "verified_generic"


def calc_target_coverage(target: Target) -> dict:
    checks = []
    matched = 0
    for p in target.required_patterns:
        files = resolve(p)
        valid_files: list[str] = []
        invalid_files: list[dict] = []
        for file_path in files:
            ok_file, reason = validate_file_integrity(file_path)
            if ok_file:
                valid_files.append(file_path)
            else:
                invalid_files.append({"file": file_path, "reason": reason})

        ok = len(valid_files) > 0
        if ok:
            matched += 1
        checks.append(
            {
                "pattern": p,
                "matched": ok,
                "matched_by_integrity": ok,
                "files_found_count": len(files),
                "valid_files_count": len(valid_files),
                "invalid_files_count": len(invalid_files),
                "files": valid_files[:10],
                "invalid_files": invalid_files[:10],
            }
        )
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
        "integrity_validation": {
            "enabled": True,
            "stub_tokens": list(STUB_TOKENS),
            "min_bytes_by_suffix": MIN_BYTES_BY_SUFFIX,
        },
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
            if c["invalid_files_count"] > 0:
                invalid_preview = ", ".join(
                    [f"{item['file']} ({item['reason']})" for item in c["invalid_files"][:3]]
                )
                lines.append(
                    f"- {r['id']}: invalid files for `{c['pattern']}` -> {invalid_preview}"
                )
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
