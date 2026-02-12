#!/usr/bin/env python3
"""
Compute priority-saturation risk ranking by agency.

Inputs:
  - out/us_audit/gao_recommendations_aging/gao_recommendations_aging.csv
  - out/us_audit/federal_ingest/usaspending_*.json (optional spend proxy)

Outputs:
  - out/us_audit/priority_saturation/priority_saturation.csv
  - out/us_audit/priority_saturation/priority_saturation.json
  - out/us_audit/priority_saturation/priority_saturation.md
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import re
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from statistics import median
from typing import Dict, List


ROOT = Path("/Users/josephocasio/Documents/New project")
DEFAULT_GAO_AGING = ROOT / "out" / "us_audit" / "gao_recommendations_aging" / "gao_recommendations_aging.csv"
DEFAULT_FED_INGEST = ROOT / "out" / "us_audit" / "federal_ingest"
OUT_DIR = ROOT / "out" / "us_audit" / "priority_saturation"


def norm(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", " ", (s or "").lower()).strip()


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


def to_bool_open(value: str) -> bool:
    v = str(value).strip().lower()
    return v in {"1", "true", "yes", "open"}


def to_bool_priority(value: str) -> bool:
    v = str(value).strip().lower()
    return v in {"1", "true", "yes", "y", "priority"}


def load_gao_rows(path: Path) -> List[dict]:
    if not path.exists():
        return []
    rows: List[dict] = []
    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(row)
    return rows


def latest_usaspending_files(fed_dir: Path) -> List[Path]:
    if not fed_dir.exists():
        return []
    files = [p for p in fed_dir.glob("usaspending_*.json") if p.is_file() and not p.name.endswith(".meta.json")]
    # keep deterministic + prefer newer
    return sorted(files, key=lambda p: p.stat().st_mtime)


def spend_proxy_by_agency(files: List[Path]) -> Dict[str, float]:
    totals: Dict[str, float] = defaultdict(float)
    for p in files:
        try:
            wrapped = json.loads(p.read_text(encoding="utf-8"))
            payload = wrapped.get("data", {})
            results = payload.get("results", [])
            for r in results:
                agency = canonical_agency(str(r.get("Awarding Agency", "")))
                amount = r.get("Transaction Amount", r.get("Award Amount", 0))
                try:
                    totals[agency] += float(amount)
                except Exception:
                    continue
        except Exception:
            continue
    return dict(totals)


def minmax_scale(values: Dict[str, float]) -> Dict[str, float]:
    if not values:
        return {}
    nums = list(values.values())
    lo, hi = min(nums), max(nums)
    if math.isclose(lo, hi):
        return {k: 0.5 for k in values}
    return {k: (v - lo) / (hi - lo) for k, v in values.items()}


def build_rows(gao_rows: List[dict], spend_proxy: Dict[str, float]) -> List[dict]:
    by_agency: Dict[str, dict] = {}
    for row in gao_rows:
        agency = canonical_agency(row.get("agency", "") or row.get("Agency", "Unknown"))
        is_open = to_bool_open(row.get("is_open", row.get("status", "")))
        if not is_open:
            continue
        if agency not in by_agency:
            by_agency[agency] = {
                "agency": agency,
                "open_count": 0,
                "priority_open_count": 0,
                "critical_flag_open_count": 0,
                "open_ages": [],
            }
        by_agency[agency]["open_count"] += 1
        if to_bool_priority(row.get("priority", "")):
            by_agency[agency]["priority_open_count"] += 1
        flags_raw = str(row.get("risk_flags", "")).strip()
        if flags_raw:
            by_agency[agency]["critical_flag_open_count"] += 1
        age = row.get("age_days", "")
        try:
            by_agency[agency]["open_ages"].append(int(float(age)))
        except Exception:
            pass

    # include agencies with spend proxy even if no GAO rows
    for agency in spend_proxy:
        if agency not in by_agency:
            by_agency[agency] = {
                "agency": agency,
                "open_count": 0,
                "priority_open_count": 0,
                "critical_flag_open_count": 0,
                "open_ages": [],
            }

    # compute metrics
    rows: List[dict] = []
    for agency, rec in by_agency.items():
        open_count = rec["open_count"]
        priority_open = rec["priority_open_count"]
        critical_open = rec["critical_flag_open_count"]
        ages = rec["open_ages"]
        pr_sat = (priority_open / open_count * 100.0) if open_count else 0.0
        critical_sat = (critical_open / open_count * 100.0) if open_count else 0.0
        med_age = float(median(ages)) if ages else 0.0
        p90_age = float(sorted(ages)[int(max(0, (len(ages) - 1) * 0.9))]) if ages else 0.0
        rows.append(
            {
                "agency": agency,
                "open_count": open_count,
                "priority_open_count": priority_open,
                "critical_flag_open_count": critical_open,
                "priority_saturation_pct": round(pr_sat, 2),
                "critical_flag_saturation_pct": round(critical_sat, 2),
                "median_open_age_days": round(med_age, 2),
                "p90_open_age_days": round(p90_age, 2),
                "spend_proxy_usd": round(spend_proxy.get(agency, 0.0), 2),
            }
        )

    # normalized risk score
    pr_norm = minmax_scale({r["agency"]: r["priority_saturation_pct"] for r in rows})
    critical_norm = minmax_scale({r["agency"]: r["critical_flag_saturation_pct"] for r in rows})
    age_norm = minmax_scale({r["agency"]: r["median_open_age_days"] for r in rows})
    spend_norm = minmax_scale({r["agency"]: math.log1p(r["spend_proxy_usd"]) for r in rows})
    for r in rows:
        a = r["agency"]
        risk = (
            0.40 * pr_norm.get(a, 0.0)
            + 0.20 * critical_norm.get(a, 0.0)
            + 0.25 * age_norm.get(a, 0.0)
            + 0.15 * spend_norm.get(a, 0.0)
        )
        r["risk_score"] = round(risk, 4)

    rows.sort(key=lambda x: x["risk_score"], reverse=True)
    return rows


def write_outputs(rows: List[dict], gao_path: Path, fed_files: List[Path]) -> tuple[Path, Path, Path]:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    csv_out = OUT_DIR / "priority_saturation.csv"
    json_out = OUT_DIR / "priority_saturation.json"
    md_out = OUT_DIR / "priority_saturation.md"

    headers = [
        "agency",
        "open_count",
        "priority_open_count",
        "critical_flag_open_count",
        "priority_saturation_pct",
        "critical_flag_saturation_pct",
        "median_open_age_days",
        "p90_open_age_days",
        "spend_proxy_usd",
        "risk_score",
    ]
    with csv_out.open("w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=headers)
        w.writeheader()
        for row in rows:
            w.writerow(row)

    payload = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "inputs": {
            "gao_aging_csv": str(gao_path),
            "usaspending_files_count": len(fed_files),
        },
        "rows": rows,
    }
    json_out.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    lines = [
        "# Priority Saturation Analysis",
        "",
        f"Generated: {payload['generated_at_utc']}",
        "",
        "## Ranking",
    ]
    if not rows:
        lines.append("- No rows available (missing GAO aging input).")
    else:
        for idx, r in enumerate(rows, start=1):
            lines.append(
                f"{idx}. **{r['agency']}** | risk={r['risk_score']} | "
                f"open={r['open_count']} | priority_sat={r['priority_saturation_pct']}% | "
                f"median_age={r['median_open_age_days']}d"
            )
    lines.append("")
    lines.append("## Inputs")
    lines.append(f"- GAO aging csv: `{gao_path}`")
    lines.append(f"- USAspending files considered: {len(fed_files)}")
    md_out.write_text("\n".join(lines) + "\n", encoding="utf-8")

    return csv_out, json_out, md_out


def main() -> int:
    parser = argparse.ArgumentParser(description="Compute agency priority-saturation risk ranking.")
    parser.add_argument("--gao-aging-csv", default=str(DEFAULT_GAO_AGING))
    parser.add_argument("--federal-ingest-dir", default=str(DEFAULT_FED_INGEST))
    args = parser.parse_args()

    gao_path = Path(args.gao_aging_csv)
    fed_dir = Path(args.federal_ingest_dir)

    gao_rows = load_gao_rows(gao_path)
    fed_files = latest_usaspending_files(fed_dir)
    spend_proxy = spend_proxy_by_agency(fed_files)
    rows = build_rows(gao_rows, spend_proxy)
    out_csv, out_json, out_md = write_outputs(rows, gao_path=gao_path, fed_files=fed_files)

    print(f"rows: {len(rows)}")
    print(f"wrote: {out_csv}")
    print(f"wrote: {out_json}")
    print(f"wrote: {out_md}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
