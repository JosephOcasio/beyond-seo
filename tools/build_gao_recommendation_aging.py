#!/usr/bin/env python3
"""
Build GAO recommendations aging outputs from exported CSV files.

Input:
  - One or more GAO recommendation CSV exports in inbox/us_sources or manual vault raw folder.

Output:
  - out/us_audit/gao_recommendations_aging/gao_recommendations_aging.csv
  - out/us_audit/gao_recommendations_aging/gao_recommendations_aging_summary.json
  - out/us_audit/gao_recommendations_aging/gao_recommendations_aging.md
"""

from __future__ import annotations

import argparse
import csv
import json
import re
from collections import Counter, defaultdict
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from statistics import median
from typing import Optional


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_INPUT_GLOBS = [
    "inbox/us_sources/*gao*recommend*.csv",
    "inbox/us_sources/*recommend*.csv",
    "out/us_audit/manual_vault/raw/*gao*recommend*.csv",
]
OUT_DIR = ROOT / "out" / "us_audit" / "gao_recommendations_aging"

RISK_KEYWORDS = {
    # Requested explicit flags
    "DOD_GPPIC": [
        "government property in possession of contractors",
        "gpipc",
        "contractor property",
        "property in possession of contractors",
    ],
    "SSA_DEATH_SUSPENSE": [
        "death suspense",
        "deceased beneficiary",
        "deceased beneficiaries",
        "death master file",
        "death information",
    ],
    # Helpful adjacent lag vectors
    "IRS_AMENDED_RETURNS": ["amended return", "manual amended return"],
    "IRS_IDENTITY_THEFT": ["identity theft", "identity protection pin", "id theft"],
}


@dataclass
class Rec:
    rec_id: str
    title: str
    agency: str
    status: str
    priority: str
    opened_on: Optional[datetime]
    age_days: Optional[int]
    bucket: str
    risk_flags: list[str]
    source_file: str


def norm(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", " ", (s or "").strip().lower()).strip()


def pick(row: dict[str, str], candidates: list[str]) -> str:
    # direct key match first
    for c in candidates:
        if c in row and str(row[c]).strip():
            return str(row[c]).strip()
    # normalized header fallback
    normalized = {norm(k): v for k, v in row.items()}
    for c in candidates:
        v = normalized.get(norm(c), "")
        if str(v).strip():
            return str(v).strip()
    return ""


def parse_date(value: str) -> Optional[datetime]:
    v = (value or "").strip()
    if not v:
        return None
    for fmt in (
        "%Y-%m-%d",
        "%m/%d/%Y",
        "%m/%d/%y",
        "%Y/%m/%d",
        "%b %d, %Y",
        "%B %d, %Y",
    ):
        try:
            dt = datetime.strptime(v, fmt)
            return dt.replace(tzinfo=timezone.utc)
        except ValueError:
            pass
    # ISO fallback
    try:
        return datetime.fromisoformat(v.replace("Z", "+00:00")).astimezone(timezone.utc)
    except ValueError:
        return None


def is_open(status: str) -> bool:
    s = norm(status)
    if not s:
        return True
    open_terms = ["open", "pending", "in process", "not implemented", "partially implemented"]
    closed_terms = ["closed", "implemented", "complete", "superseded"]
    if any(t in s for t in closed_terms):
        return False
    if any(t in s for t in open_terms):
        return True
    return True


def bucket_for(age_days: Optional[int]) -> str:
    if age_days is None:
        return "unknown"
    if age_days <= 90:
        return "0-90"
    if age_days <= 180:
        return "91-180"
    if age_days <= 365:
        return "181-365"
    if age_days <= 730:
        return "366-730"
    return "731+"


def canonical_agency(name: str) -> str:
    n = norm(name)
    if "defense" in n or n == "dod":
        return "Department of Defense"
    if "internal revenue service" in n or n == "irs":
        return "Internal Revenue Service"
    if "social security" in n or n == "ssa":
        return "Social Security Administration"
    if "treasury" in n:
        return "Department of the Treasury"
    return (name or "Unknown").strip() or "Unknown"


def detect_risk_flags(searchable_text: str, agency: str) -> list[str]:
    text = norm(searchable_text)
    a = canonical_agency(agency)
    flags: list[str] = []

    def has_any(terms: list[str]) -> bool:
        return any(norm(t) in text for t in terms)

    # Agency-aware tagging to keep signal cleaner.
    if a == "Department of Defense" and has_any(RISK_KEYWORDS["DOD_GPPIC"]):
        flags.append("DOD_GPPIC")
    if a == "Social Security Administration" and has_any(RISK_KEYWORDS["SSA_DEATH_SUSPENSE"]):
        flags.append("SSA_DEATH_SUSPENSE")
    if a == "Internal Revenue Service" and has_any(RISK_KEYWORDS["IRS_AMENDED_RETURNS"]):
        flags.append("IRS_AMENDED_RETURNS")
    if a == "Internal Revenue Service" and has_any(RISK_KEYWORDS["IRS_IDENTITY_THEFT"]):
        flags.append("IRS_IDENTITY_THEFT")

    return flags


def resolve_inputs(globs: list[str]) -> list[Path]:
    files: set[Path] = set()
    for pattern in globs:
        for p in ROOT.glob(pattern):
            if p.is_file():
                files.add(p.resolve())
    return sorted(files)


def parse_files(paths: list[Path], today: datetime) -> list[Rec]:
    out: list[Rec] = []
    for path in paths:
        with path.open("r", encoding="utf-8-sig", newline="") as f:
            reader = csv.DictReader(f)
            for row in reader:
                rec_id = pick(row, ["Recommendation Number", "Recommendation ID", "id", "rec_id"])
                title = pick(row, ["Recommendation", "Title", "recommendation_text", "text"])
                agency = pick(row, ["Agency", "agency", "Department"])
                status = pick(row, ["Status", "Recommendation Status", "status"])
                priority = pick(row, ["Priority Recommendation", "priority_recommendation", "Priority"])
                opened_raw = pick(
                    row,
                    [
                        "Date Opened",
                        "Open Date",
                        "Date Issued",
                        "Issued Date",
                        "Recommendation Date",
                    ],
                )
                opened_dt = parse_date(opened_raw)
                age_days = (today - opened_dt).days if opened_dt else None
                searchable_text = " ".join(str(v) for v in row.values())
                risk_flags = detect_risk_flags(searchable_text=searchable_text, agency=agency)
                out.append(
                    Rec(
                        rec_id=rec_id,
                        title=title,
                        agency=canonical_agency(agency),
                        status=status,
                        priority=priority,
                        opened_on=opened_dt,
                        age_days=age_days,
                        bucket=bucket_for(age_days),
                        risk_flags=risk_flags,
                        source_file=path.name,
                    )
                )
    return out


def write_outputs(records: list[Rec], inputs: list[Path]) -> tuple[Path, Path, Path]:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    csv_path = OUT_DIR / "gao_recommendations_aging.csv"
    json_path = OUT_DIR / "gao_recommendations_aging_summary.json"
    md_path = OUT_DIR / "gao_recommendations_aging.md"

    # detailed rows
    with csv_path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(
            [
                "rec_id",
                "title",
                "agency",
                "status",
                "is_open",
                "priority",
                "opened_on",
                "age_days",
                "age_bucket",
                "risk_flags",
                "source_file",
            ]
        )
        for r in records:
            writer.writerow(
                [
                    r.rec_id,
                    r.title,
                    r.agency,
                    r.status,
                    1 if is_open(r.status) else 0,
                    r.priority,
                    r.opened_on.date().isoformat() if r.opened_on else "",
                    r.age_days if r.age_days is not None else "",
                    r.bucket,
                    ";".join(r.risk_flags),
                    r.source_file,
                ]
            )

    open_ages = [r.age_days for r in records if is_open(r.status) and r.age_days is not None]
    open_count = sum(1 for r in records if is_open(r.status))
    closed_count = len(records) - open_count

    by_bucket = Counter(r.bucket for r in records if is_open(r.status))
    by_agency_open = Counter((r.agency or "Unknown") for r in records if is_open(r.status))
    by_priority_open = Counter((r.priority or "Unknown") for r in records if is_open(r.status))
    by_flag_open = Counter(flag for r in records if is_open(r.status) for flag in r.risk_flags)
    by_flag_agency_open: dict[str, Counter] = defaultdict(Counter)
    for r in records:
        if not is_open(r.status):
            continue
        for flag in r.risk_flags:
            by_flag_agency_open[r.agency][flag] += 1

    flagged_open_records = []
    for r in records:
        if is_open(r.status) and r.risk_flags:
            flagged_open_records.append(
                {
                    "rec_id": r.rec_id,
                    "agency": r.agency,
                    "title": r.title,
                    "status": r.status,
                    "age_days": r.age_days,
                    "risk_flags": r.risk_flags,
                    "source_file": r.source_file,
                }
            )
    flagged_open_records.sort(key=lambda x: (x["age_days"] or -1), reverse=True)

    summary = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "input_files": [str(p) for p in inputs],
        "total_records": len(records),
        "open_records": open_count,
        "closed_records": closed_count,
        "open_median_age_days": median(open_ages) if open_ages else None,
        "open_age_buckets": dict(by_bucket),
        "open_by_agency_top_20": by_agency_open.most_common(20),
        "open_by_priority": dict(by_priority_open),
        "open_flag_counts": dict(by_flag_open),
        "open_flag_counts_by_agency": {agency: dict(counter) for agency, counter in by_flag_agency_open.items()},
        "flagged_open_records_top_50": flagged_open_records[:50],
    }
    json_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")

    lines = [
        "# GAO Recommendations Aging",
        "",
        f"Generated: {summary['generated_at_utc']}",
        "",
        "## Coverage",
        f"- Input files: {len(inputs)}",
        f"- Total recommendations: {summary['total_records']}",
        f"- Open recommendations: {summary['open_records']}",
        f"- Closed recommendations: {summary['closed_records']}",
        f"- Median age of open recommendations (days): {summary['open_median_age_days']}",
        "",
        "## Open Recommendation Age Buckets",
    ]
    for bucket in ["0-90", "91-180", "181-365", "366-730", "731+", "unknown"]:
        lines.append(f"- {bucket}: {summary['open_age_buckets'].get(bucket, 0)}")
    lines.append("")
    lines.append("## Top Agencies by Open Recommendation Count")
    for agency, count in summary["open_by_agency_top_20"][:10]:
        lines.append(f"- {agency}: {count}")
    lines.append("")
    lines.append("## Flagged Risk Vectors (Open Recommendations)")
    if summary["open_flag_counts"]:
        for flag, count in sorted(summary["open_flag_counts"].items(), key=lambda x: x[1], reverse=True):
            lines.append(f"- {flag}: {count}")
    else:
        lines.append("- No flagged risk-vector keywords found in open recommendations.")
    lines.append("")
    lines.append("## Flagged Open Recommendations (Top 10 by Age)")
    if summary["flagged_open_records_top_50"]:
        for item in summary["flagged_open_records_top_50"][:10]:
            lines.append(
                f"- `{item.get('rec_id')}` | {item.get('agency')} | age={item.get('age_days')}d | "
                f"flags={','.join(item.get('risk_flags', []))} | {item.get('title')}"
            )
    else:
        lines.append("- None.")
    lines.append("")
    lines.append("## Notes")
    lines.append("- This report is only as complete as the GAO recommendation CSV exports provided.")
    lines.append("- If no CSV exports are found, outputs are generated with zero counts.")
    md_path.write_text("\n".join(lines) + "\n", encoding="utf-8")

    return csv_path, json_path, md_path


def main() -> int:
    parser = argparse.ArgumentParser(description="Build GAO recommendation aging outputs from local CSV exports.")
    parser.add_argument(
        "--input-glob",
        action="append",
        default=[],
        help="Custom input glob (relative to project root). Can be passed multiple times.",
    )
    args = parser.parse_args()

    patterns = args.input_glob if args.input_glob else DEFAULT_INPUT_GLOBS
    paths = resolve_inputs(patterns)
    today = datetime.now(timezone.utc)
    records = parse_files(paths, today=today) if paths else []
    out_csv, out_json, out_md = write_outputs(records=records, inputs=paths)

    print(f"inputs: {len(paths)}")
    print(f"records: {len(records)}")
    print(f"wrote: {out_csv}")
    print(f"wrote: {out_json}")
    print(f"wrote: {out_md}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
