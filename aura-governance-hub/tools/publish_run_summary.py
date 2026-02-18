#!/usr/bin/env python3
"""Publish a compact run summary JSON for the public skeleton UI."""

from __future__ import annotations

import argparse
import csv
import json
from collections import Counter
from pathlib import Path


def build_summary(run_dir: Path) -> dict:
    admissibility_path = run_dir / "admissibility.json"
    hotspots_path = run_dir / "hotspots.csv"

    if not admissibility_path.exists() or not hotspots_path.exists():
        missing = [
            str(p)
            for p in (admissibility_path, hotspots_path)
            if not p.exists()
        ]
        raise FileNotFoundError(f"Missing required files: {', '.join(missing)}")

    admissibility = json.loads(admissibility_path.read_text(encoding="utf-8"))

    hotspot_rows = []
    with hotspots_path.open("r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            row["value"] = int(float(row["value"]))
            row["windowing_size"] = int(float(row["windowing_size"]))
            for key in ("f_obs", "mu_null", "sigma_null", "z", "p_emp", "q_fdr", "p_bonf"):
                row[key] = float(row[key])
            hotspot_rows.append(row)

    q010_positive = [r for r in hotspot_rows if r["z"] > 0 and r["q_fdr"] <= 0.10]
    top_q010 = sorted(q010_positive, key=lambda r: (r["q_fdr"], r["p_emp"]))[:12]

    return {
        "run_id": run_dir.name,
        "generated_from": str(run_dir),
        "stats": {
            "hotspot_rows": len(hotspot_rows),
            "admissibility_records": len(admissibility),
            "class_counts": dict(Counter(r["class"] for r in admissibility)),
            "w2_true": sum(1 for r in admissibility if r.get("w2")),
            "w4_true": sum(1 for r in admissibility if r.get("w4")),
            "q010_positive_rows": len(q010_positive),
            "null_breakdown_q010_positive": dict(Counter(r["null_model"] for r in q010_positive)),
        },
        "top_hotspots_q010": [
            {
                "value": r["value"],
                "null_model": r["null_model"],
                "q_fdr": round(r["q_fdr"], 6),
                "p_emp": round(r["p_emp"], 6),
                "z": round(r["z"], 3),
                "f_obs": r["f_obs"],
                "mu_null": round(r["mu_null"], 3),
            }
            for r in top_q010
        ],
        "notes": [
            "W3 is conservative/disabled by default in cfmga.py, so classes stay C until W3 logic is implemented.",
            "Use this for public visibility; keep publication claims tied to full gate validation.",
        ],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Publish CFMGA run summary JSON for UI")
    parser.add_argument("--run-dir", required=True, help="Path to a run folder containing hotspots.csv + admissibility.json")
    parser.add_argument(
        "--out",
        default="out/latest/summary.json",
        help="Output JSON path (default: out/latest/summary.json)",
    )
    args = parser.parse_args()

    run_dir = Path(args.run_dir).expanduser().resolve()
    out_path = Path(args.out).expanduser().resolve()

    summary = build_summary(run_dir)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(f"Wrote {out_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
